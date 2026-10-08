# NAC / WPA3-Enterprise — Phase 1

The Laravel/Filament app is the **NAC management plane**, NOT the EAP authenticator.
FreeRADIUS must authenticate Wi-Fi devices cryptographically via EAP-TLS,
then call this API from a trusted private network to decide whether the
verified client certificate is authorized and which VLAN should be returned.

## Implemented

- Disabled-by-default SSIDs and per-SSID role-based VLAN policies.
- Separate \`nac_clients\` (end-user endpoints) with optional CMDB association;
  no accidental conversion of infrastructure \`devices\` into personal endpoints.
- Certificate metadata, local revocation, expiry checks. No private keys.
- Audit decisions in \`nac_auth_events\` with retention via Laravel scheduler.
- Private service-key authentication, optional RADIUS source IP allowlist.
- Super-admin-only Filament pages, no NAC delete actions.

## Production prerequisites (not implemented in Phase 1)

1. A real SSO/OIDC identity provider and a secure onboarding workflow.
   Filament's current local login does **not** establish interoperable SSO.
2. Dedicated FreeRADIUS 3.2+ services and a managed CA/PKI (including CA trust,
   renewal, CRL/OCSP/revocation strategy and private-key protections).
3. Validate issuer+serial format against the *verified leaf certificate*
   attributes as emitted by the installed FreeRADIUS version. The API should
   ONLY be called after EAP-TLS certificate-chain and possession checks succeed.
   Never trust a client-supplied \`tls_verified\` flag. It must be set by
   trusted FreeRADIUS integration code after successful EAP-TLS verification.
4. TLS on the internal API endpoint, secret rotation, RADIUS host IP allowlist,
   firewall ingress control, accurate proxy/IP trust configuration, log redaction,
   secure NTP and redundant RADIUS instances before production.
5. UniFi WPA3-Enterprise/RADIUS profile, correct RADIUS-assigned VLAN support,
   VLAN trunk ports, MikroTik routing/firewall policy, accounting/CoA testing.
   RADIUS CoA and active session termination are **not** part of Phase 1.
6. Use staging with real AP and supplicants; confirm a disconnected/offline API
   causes **denial**, never access. Confirm EAP-TLS success and revocation behavior.

## Environment

\`\`\`dotenv
NAC_RADIUS_API_KEY=GENERATE_A_RANDOM_SECRET_OF_AT_LEAST_32_CHARACTERS
NAC_RADIUS_ALLOWED_IPS=10.10.0.10,10.10.0.11
NAC_AUTH_HISTORY_RETENTION_DAYS=90
\`\`\`

A missing / short key always rejects. If \`NAC_RADIUS_ALLOWED_IPS\` is empty,
only the API key is checked; production SHOULD configure a private-network
allowlist. Ensure the Laravel queue workers and scheduler already required by
the CMDB are running. Do not commit actual secrets.

## Authorization API — trusted adapter contract

POST \`/api/nac/radius/authorize\`

Headers: \`X-NAC-Radius-Key: <secret>\`, \`Content-Type: application/json\`

Request example (adapter sets verified status **only** after EAP-TLS success):

\`\`\`json
{
  "ssid": "EduOS-Secure",
  "certificate_issuer": "/CN=Educational-CA",
  "certificate_serial": "A01B",
  "tls_verified": true,
  "nas_identifier": "unifi-ap-01",
  "calling_station_id": "00-11-22-33-44-55"
}
\`\`\`

Issuer must match the stored issuer exactly (surrounding whitespace trimmed).
Serial is uppercased and trimmed. Populate database metadata from the same
trusted issuing CA / certificate parser. Do not rely on MAC for identity.

HTTP 200 = authorized. Response is FreeRADIUS 3.x rlm_rest-compatible JSON
reply attributes (example VLAN 30):

\`\`\`json
{
  "reply:Tunnel-Type": "VLAN",
  "reply:Tunnel-Medium-Type": "IEEE-802",
  "reply:Tunnel-Private-Group-Id": "30"
}
\`\`\`

HTTP 403 = deny (no VLAN assigned). HTTP 401 = invalid service key/IP.
HTTP 422 = malformed payload. HTTP 5xx = service failure. Configure
FreeRADIUS unlang and rlm_rest to reject **every** non-200/error condition
and to make the EAP-TLS acceptance decision itself. No \`Auth-Type := Accept\`
is returned by the API.

Policies: matching enabled SSID, non-revoked certificate within validity,
client status \`active\`, user \`is_active\`, first enabled policy ordered by
highest \`priority\` then lowest ID; \`role_name=null\` is an explicit fallback
matching every active user, not an implicit allowance. No match means deny.

**Important:** In a real FreeRADIUS deployment, rlm_rest default \`body=json\`
sends RADIUS attribute maps, *not necessarily this flat JSON contract*.
Configure an explicit request-body adapter or a translation service; do not
assume pointing rlm_rest at this URL without translation is sufficient.
The exact EAP-TLS hook and reply propagation must be verified with
\`freeradius -X\` before attaching to UniFi.

## Installation / validation

\`\`\`bash
php artisan migrate --force
php artisan filament:optimize-clear
php artisan optimize:clear
php artisan test --filter=Nac
php artisan schedule:list
\`\`\`

Keep all NAC entries disabled in production until FreeRADIUS and certificate
verification are integrated. Rollout should be per test SSID; don't change
existing SSIDs, VLANs, router or access-point settings automatically.
