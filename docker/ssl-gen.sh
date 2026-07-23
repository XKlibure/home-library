#!/bin/sh
# ─────────────────────────────────────────────────────────────────────────────
# ssl-gen.sh  —  Self-signed TLS certificate generator for Bookoholik
#
# Usage:
#   ./docker/ssl-gen.sh                        # uses localhost + 127.0.0.1
#   ./docker/ssl-gen.sh 192.168.1.12           # LAN IP
#   ./docker/ssl-gen.sh 192.168.1.12 myhome.local  # IP + hostname
#
# Certificates are placed in:  ./certs/server.crt  and  ./certs/server.key
#
# After running this script, start the app with:
#   docker compose -f docker-compose.ssl.yml up -d --build
# ─────────────────────────────────────────────────────────────────────────────
set -e

CERTS_DIR="$(cd "$(dirname "$0")/.." && pwd)/certs"
mkdir -p "${CERTS_DIR}"

# ── Collect SANs from arguments ──────────────────────────────────────────────
IP1="${1:-127.0.0.1}"
EXTRA="${2:-}"

SAN="IP:${IP1},IP:127.0.0.1"
CN="${IP1}"

if [ -n "${EXTRA}" ]; then
    # If the extra arg looks like an IP, add as IP SAN; otherwise as DNS SAN
    case "${EXTRA}" in
        [0-9]*.[0-9]*.[0-9]*.[0-9]*)
            SAN="${SAN},IP:${EXTRA}"
            ;;
        *)
            SAN="${SAN},DNS:${EXTRA},DNS:localhost"
            CN="${EXTRA}"
            ;;
    esac
else
    SAN="${SAN},DNS:localhost"
fi

echo ""
echo "Generating self-signed certificate..."
echo "  CN  : ${CN}"
echo "  SAN : ${SAN}"
echo "  Dir : ${CERTS_DIR}"
echo ""

openssl req -x509 \
    -newkey rsa:4096 \
    -sha256 \
    -days 3650 \
    -nodes \
    -keyout "${CERTS_DIR}/server.key" \
    -out    "${CERTS_DIR}/server.crt" \
    -subj   "/CN=${CN}/O=Bookoholik/OU=Home Library" \
    -addext "subjectAltName=${SAN}"

chmod 600 "${CERTS_DIR}/server.key"
chmod 644 "${CERTS_DIR}/server.crt"

echo ""
echo "✅  Certificate generated:"
echo "    ${CERTS_DIR}/server.crt"
echo "    ${CERTS_DIR}/server.key"
echo ""
echo "Next steps:"
echo "  1. Copy .env.ssl.example to .env and fill in your values."
echo "  2. Start the SSL stack:"
echo "       docker compose -f docker-compose.ssl.yml up -d --build"
echo ""
echo "To trust this certificate on macOS:"
echo "  sudo security add-trusted-cert -d -r trustRoot \\"
echo "    -k /Library/Keychains/System.keychain ${CERTS_DIR}/server.crt"
echo ""
echo "For iOS: email server.crt to your device → install as profile."
