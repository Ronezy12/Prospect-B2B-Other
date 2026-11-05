#!/usr/bin/env bash
# Lance le module dans GitHub Codespaces et affiche l'adresse et le mot de passe à transmettre.
set -e
cd "$(dirname "$0")/.."

# Adresse publique du Codespace (HTTPS fourni par GitHub).
if [ -n "${CODESPACE_NAME:-}" ]; then
  export APP_URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
  export TRUSTED_PROXIES="*"
fi

# Mot de passe privé des comptes de démonstration : créé une fois, conservé dans le Codespace.
if [ ! -f .devcontainer/.mot-de-passe ]; then
  echo "AR-$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 14)" > .devcontainer/.mot-de-passe
fi
export PM_DEMO_PASSWORD="$(cat .devcontainer/.mot-de-passe)"

docker compose up -d --build

echo ""
echo "=============================================================="
echo " Le site démarre (1 à 2 minutes la première fois)."
echo " Adresse : ${APP_URL:-http://localhost:8000}"
echo " Comptes : admin@demo.test, commercial@demo.test, marketing@demo.test…"
echo " Mot de passe : ${PM_DEMO_PASSWORD}"
echo " Pour le partager : onglet PORTS › port 8000 › clic droit ›"
echo " « Visibilité du port » › Public, puis copiez l'adresse."
echo "=============================================================="
