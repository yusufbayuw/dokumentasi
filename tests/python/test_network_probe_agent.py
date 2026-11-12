import os
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", "scripts")))
import network_probe_agent as agent


class AgentSafetyTests(unittest.TestCase):
    def setUp(self):
        self.config = {
            "location_id": 42,
            "allowed_cidrs": ["10.10.0.0/16"],
            "allowed_domains": ["internal.example.org"],
            "samples": 3,
            "timeout_seconds": 2,
        }

    def test_public_ip_and_unapproved_tcp_port_are_rejected(self):
        self.assertFalse(agent.approved_target({
            "probe_type": "icmp", "address": "8.8.8.8", "port": None
        }, self.config))
        self.assertFalse(agent.approved_target({
            "probe_type": "tcp", "address": "10.10.1.1", "port": 22
        }, self.config))
        self.assertTrue(agent.approved_target({
            "probe_type": "tcp", "address": "10.10.1.1", "port": 443
        }, self.config))

    def test_https_requires_exact_whitelisted_host_and_tls(self):
        self.assertTrue(agent.approved_target({
            "probe_type": "https",
            "address": "https://internal.example.org/health",
        }, self.config))
        for url in [
            "http://internal.example.org/health",
            "https://internal.example.org.attacker.invalid/",
            "https://user:secret@internal.example.org/health",
        ]:
            self.assertFalse(agent.approved_target({
                "probe_type": "https", "address": url
            }, self.config))

    def test_redirect_handler_never_follows_redirects(self):
        no_redirect = agent.NoRedirect()
        self.assertIsNone(
            no_redirect.redirect_request(None, None, 302, "Found", {}, "https://evil.invalid")
        )

    def test_payload_contains_location_id_and_unique_uuid(self):
        target = {
            "target_id": 1, "probe_type": "icmp",
            "address": "10.10.1.1", "port": None,
        }
        with patch.object(agent, "ping", return_value={
            "status": "up", "samples_sent": 3,
            "samples_received": 3, "latency_ms": 4.5,
            "packet_loss_pct": 0,
        }):
            result = agent.report_for(target, self.config)
        self.assertEqual(result["location_id"], 42)
        self.assertEqual(result["target_id"], 1)
        self.assertEqual(result["status"], "up")
        self.assertEqual(len(result["report_id"]), 36)


if __name__ == "__main__":
    unittest.main()
