#!/usr/bin/env python3
"""Minimal distributed Network Quality probe agent (Python 3, stdlib only).

Configure static allowlists and target definitions locally. Report over HTTPS
using a per-location bearer token stored in the NQ_PROBE_TOKEN environment.
No dynamic remote commands or arbitrary remote-provided destinations.
"""
from __future__ import annotations

import argparse
import datetime as dt
import ipaddress
import json
import os
import re
import socket
import ssl
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

ALLOWED_TCP_PORTS = {53, 80, 443, 8080, 8443}
ALLOWED_HTTPS_PORTS = {443, 8443}


def approved_target(target: dict, config: dict) -> bool:
    kind = str(target.get("probe_type", ""))
    address = str(target.get("address", ""))
    port = target.get("port")
    networks = [ipaddress.ip_network(v) for v in config.get("allowed_cidrs", [])]
    domains = {str(v).lower().rstrip(".") for v in config.get("allowed_domains", [])}

    if kind in ("icmp", "tcp"):
        try:
            ip = ipaddress.ip_address(address)
        except ValueError:
            return False
        if ip.version != 4 or not any(ip in subnet for subnet in networks):
            return False
        if kind == "icmp":
            return port is None
        return type(port) is int and port in ALLOWED_TCP_PORTS

    if kind == "dns":
        return address.lower().rstrip(".") in domains and port is None

    if kind == "https":
        try:
            parsed = urllib.parse.urlsplit(address)
            return (
                parsed.scheme == "https"
                and parsed.hostname is not None
                and parsed.hostname.lower().rstrip(".") in domains
                and parsed.username is None
                and parsed.password is None
                and parsed.port in (None, *ALLOWED_HTTPS_PORTS)
            )
        except ValueError:
            return False

    return False


def ping(address: str, count: int, timeout: int) -> dict:
    try:
        completed = subprocess.run(
            ["ping", "-n", "-c", str(count), "-W", str(timeout),
             "-i", "0.2", address],
            capture_output=True,
            text=True,
            timeout=min(60, count * (timeout + 1) + 5),
            env={**os.environ, "LC_ALL": "C"},
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return {"status": "error", "error_code": "icmp_unavailable"}

    output = completed.stdout + "\n" + completed.stderr
    matched = re.search(
        r"(\d+)\s+packets transmitted,\s+(\d+)\s+(?:packets\s+)?received",
        output,
        re.I,
    )
    if not matched:
        return {"status": "error", "error_code": "icmp_unavailable"}

    sent, received = (int(matched.group(1)), int(matched.group(2)))
    if sent < 1 or received > sent:
        return {"status": "error", "error_code": "icmp_invalid_result"}

    times = [float(value) for value in re.findall(r"\btime[=<]\s*(\d+(?:\.\d+)?)\s*ms", output)]
    changes = [abs(times[i] - times[i - 1]) for i in range(1, len(times))]

    return {
        "status": "up" if received else "down",
        "samples_sent": sent,
        "samples_received": received,
        "packet_loss_pct": round((sent - received) * 100 / sent, 2),
        "latency_ms": round(sum(times) / len(times), 2) if times else None,
        "rtt_variation_ms": round(sum(changes) / len(changes), 2) if changes else None,
    }


def dns(address: str) -> dict:
    started = time.perf_counter()
    try:
        answers = socket.getaddrinfo(address, None, socket.AF_INET)
        return {
            "status": "up" if answers else "down",
            "latency_ms": round((time.perf_counter() - started) * 1000, 2),
        }
    except OSError:
        return {"status": "down", "error_code": "dns_lookup_failed"}


def tcp(address: str, port: int, timeout: int) -> dict:
    started = time.perf_counter()
    try:
        with socket.create_connection((address, port), timeout=timeout):
            pass
        return {
            "status": "up",
            "latency_ms": round((time.perf_counter() - started) * 1000, 2),
        }
    except OSError:
        return {"status": "down", "error_code": "tcp_connect_failed"}


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, file, code, msg, headers, newurl):
        return None


def https(url: str, timeout: int, expected_status: int | None) -> dict:
    started = time.perf_counter()
    opener = urllib.request.build_opener(
        NoRedirect(), urllib.request.HTTPSHandler(context=ssl.create_default_context())
    )
    code = None
    try:
        with opener.open(urllib.request.Request(url, method="GET"), timeout=timeout) as response:
            code = response.status
    except urllib.error.HTTPError as error:
        code = error.code
    except (OSError, urllib.error.URLError, ValueError):
        return {"status": "down", "error_code": "https_request_failed"}

    correct = code == expected_status if expected_status else (200 <= code < 400)
    return {
        "status": "up" if correct else "down",
        "latency_ms": round((time.perf_counter() - started) * 1000, 2),
        "response_code": code,
        "error_code": None if correct else "unexpected_http_status",
    }


def report_for(target: dict, config: dict) -> dict:
    if not approved_target(target, config):
        raise ValueError("Target not allowed by local probe policy")

    sample_count = max(2, min(10, int(config.get("samples", 3))))
    timeout = max(1, min(5, int(config.get("timeout_seconds", 2))))
    kind, address = target["probe_type"], target["address"]
    started = time.perf_counter()

    if kind == "icmp":
        measured = ping(address, sample_count, timeout)
    elif kind == "dns":
        measured = dns(address)
    elif kind == "tcp":
        measured = tcp(address, int(target["port"]), timeout)
    else:
        measured = https(address, timeout, target.get("expected_status"))

    return {
        "location_id": int(config["location_id"]),
        "report_id": str(uuid.uuid4()),
        "checked_at": dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds"),
        "target_id": int(target["target_id"]),
        "probe_type": kind,
        "address": address,
        "port": target.get("port"),
        "duration_ms": int((time.perf_counter() - started) * 1000),
        **measured,
    }


def send_report(base_url: str, token: str, payload: dict) -> None:
    parsed = urllib.parse.urlsplit(base_url)
    if (parsed.scheme != "https" or not parsed.hostname or parsed.username
            or parsed.password or parsed.query or parsed.fragment or parsed.path not in ("", "/")):
        raise ValueError("Central server must be an HTTPS origin URL.")

    endpoint = base_url.rstrip("/") + "/api/network-quality/probe-results"
    data = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    req = urllib.request.Request(
        endpoint,
        data=data,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": "Bearer " + token,
        },
    )

    reporter = urllib.request.build_opener(
        NoRedirect(), urllib.request.HTTPSHandler(context=ssl.create_default_context())
    )
    with reporter.open(req, timeout=12) as response:
        if response.status not in (200, 202):
            raise RuntimeError("Report not accepted by central server.")


def main() -> int:
    parser = argparse.ArgumentParser(description="Network Quality multi-location probe")
    parser.add_argument("--config", required=True, help="Path to local trusted JSON configuration")
    args = parser.parse_args()

    token = os.environ.get("NQ_PROBE_TOKEN", "")
    if len(token) < 32:
        print("NQ_PROBE_TOKEN must be configured (at least 32 characters).", file=sys.stderr)
        return 2

    with open(args.config, encoding="utf-8") as handle:
        config = json.load(handle)

    targets = config.get("targets", [])
    if not isinstance(targets, list) or len(targets) > 100:
        print("Expected 0 to 100 static probe targets.", file=sys.stderr)
        return 2

    sent = 0
    failures = 0
    for target in targets:
        try:
            payload = report_for(target, config)
            send_report(config["server_url"], token, payload)
            sent += 1
        except (KeyError, TypeError, ValueError, OSError, RuntimeError, urllib.error.HTTPError, urllib.error.URLError):
            # Do not print headers, bearer token, secrets, URLs or response body.
            failures += 1
            print("Target report failed (target_id=%s)" % str(target.get("target_id", "?")), file=sys.stderr)

    print("Reports accepted: %d; errors: %d" % (sent, failures))
    return 0 if failures == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
