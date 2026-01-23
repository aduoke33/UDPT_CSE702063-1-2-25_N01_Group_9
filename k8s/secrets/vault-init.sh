#!/bin/bash
# =====================================================
# VAULT INITIALIZATION SCRIPT
# Setup Vault for Movie Booking System
# =====================================================

set -e

VAULT_ADDR=${VAULT_ADDR:-"http://vault.vault.svc:8200"}
NAMESPACE="movie-booking"

echo "🔐 Initializing HashiCorp Vault for Movie Booking System"
echo "=================================================="

# Wait for Vault to be ready
echo "⏳ Waiting for Vault to be ready..."
until curl -s $VAULT_ADDR/v1/sys/health > /dev/null; do
    sleep 2
done
echo "✅ Vault is ready"

# Initialize Vault (only if not initialized)
if ! vault status | grep -q "Initialized.*true"; then
    echo "📦 Initializing Vault..."
    vault operator init -key-shares=5 -key-threshold=3 > /tmp/vault-init.txt
    echo "⚠️  IMPORTANT: Save the unseal keys and root token from /tmp/vault-init.txt"
fi

# Unseal Vault (manual step required in production)
echo "🔓 Vault needs to be unsealed manually with 3 of 5 keys"

# Enable KV secrets engine v2
echo "🔧 Enabling KV secrets engine..."
vault secrets enable -path=secret kv-v2 || true

# Enable Kubernetes auth
echo "🔧 Enabling Kubernetes authentication..."
vault auth enable kubernetes || true

# Configure Kubernetes auth
echo "🔧 Configuring Kubernetes auth..."
vault write auth/kubernetes/config \
    kubernetes_host="https://kubernetes.default.svc:443" \
    token_reviewer_jwt="$(cat /var/run/secrets/kubernetes.io/serviceaccount/token)" \
    kubernetes_ca_cert="$(cat /var/run/secrets/kubernetes.io/serviceaccount/ca.crt)" \
    issuer="https://kubernetes.default.svc.cluster.local"

# Create policy for movie-booking
echo "📝 Creating movie-booking policy..."
vault policy write movie-booking - <<EOF
# Read secrets for movie-booking application
path "secret/data/movie-booking/*" {
  capabilities = ["read", "list"]
}

path "secret/metadata/movie-booking/*" {
  capabilities = ["read", "list"]
}
EOF

# Create Kubernetes auth role
echo "🔧 Creating Kubernetes auth role..."
vault write auth/kubernetes/role/movie-booking-role \
    bound_service_account_names=movie-booking-sa \
    bound_service_account_namespaces=$NAMESPACE \
    policies=movie-booking \
    ttl=24h

# Store initial secrets
echo "🔐 Storing initial secrets..."

# Database credentials for each service
vault kv put secret/movie-booking/auth-service \
    database_url="postgresql+asyncpg://auth_user:CHANGE_ME_AUTH_PASSWORD@postgres-auth-service:5432/auth_db"

vault kv put secret/movie-booking/movie-service \
    database_url="postgresql+asyncpg://movie_user:CHANGE_ME_MOVIE_PASSWORD@postgres-movie-service:5432/movies_db"

vault kv put secret/movie-booking/booking-service \
    database_url="postgresql+asyncpg://booking_user:CHANGE_ME_BOOKING_PASSWORD@postgres-booking-service:5432/bookings_db"

vault kv put secret/movie-booking/payment-service \
    database_url="postgresql+asyncpg://payment_user:CHANGE_ME_PAYMENT_PASSWORD@postgres-payment-service:5432/payments_db"

vault kv put secret/movie-booking/notification-service \
    database_url="postgresql+asyncpg://notification_user:CHANGE_ME_NOTIFICATION_PASSWORD@postgres-notification-service:5432/notifications_db"

# Redis credentials
vault kv put secret/movie-booking/redis \
    password="CHANGE_ME_REDIS_PASSWORD"

# RabbitMQ credentials
vault kv put secret/movie-booking/rabbitmq \
    username="admin" \
    password="CHANGE_ME_RABBITMQ_PASSWORD"

# JWT secret (256-bit key)
JWT_SECRET=$(openssl rand -base64 32)
vault kv put secret/movie-booking/jwt \
    secret_key="$JWT_SECRET"

# Internal service token
SERVICE_TOKEN=$(openssl rand -base64 32)
vault kv put secret/movie-booking/internal \
    service_token="$SERVICE_TOKEN"

# SMTP configuration
vault kv put secret/movie-booking/smtp \
    host="smtp.gmail.com" \
    port="587" \
    username="your-email@gmail.com" \
    password="your-app-password"

echo ""
echo "✅ Vault initialization complete!"
echo "=================================================="
echo "📋 Next steps:"
echo "1. Save unseal keys securely"
echo "2. Update database passwords in Vault"
echo "3. Deploy External Secrets Operator"
echo "4. Apply external-secrets.yaml"
