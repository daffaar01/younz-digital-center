#!/usr/bin/env bash
set -Eeuo pipefail

# UpCloud first-boot provisioning for Younz Digital Center.
# Target: a fresh Ubuntu 24.04/26.04 LTS server.
# Logs: /var/log/younz-init.log

exec > >(tee -a /var/log/younz-init.log | logger -t younz-init -s 2>/dev/console) 2>&1
export DEBIAN_FRONTEND=noninteractive

readonly DEPLOY_USER="deploy"
readonly APP_DIR="/opt/younz-digital-center"
readonly DEPLOY_PUBLIC_KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJE9R8RktIl4vyfC9lBNgqz73+Iav4K+6EUtgp8MLFe8"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "This initialization script must run as root." >&2
    exit 1
fi

source /etc/os-release
if [[ "${ID:-}" != "ubuntu" ]]; then
    echo "Unsupported operating system: ${PRETTY_NAME:-unknown}. Select an Ubuntu template in UpCloud." >&2
    exit 1
fi

echo "[1/9] Updating Ubuntu and installing base packages"
apt-get update
apt-get -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y upgrade
apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    fail2ban \
    git \
    jq \
    nginx \
    openssl \
    ufw \
    unattended-upgrades

echo "[2/9] Installing Docker Engine and Docker Compose from Docker's apt repository"
for conflicting_package in docker.io docker-compose docker-compose-v2 docker-doc podman-docker containerd runc; do
    apt-get remove -y "${conflicting_package}" >/dev/null 2>&1 || true
done

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

readonly DOCKER_ARCH="$(dpkg --print-architecture)"
readonly UBUNTU_RELEASE="${UBUNTU_CODENAME:-${VERSION_CODENAME}}"
cat >/etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${UBUNTU_RELEASE}
Components: stable
Architectures: ${DOCKER_ARCH}
Signed-By: /etc/apt/keyrings/docker.asc
EOF

apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

install -d -m 0755 /etc/docker
cat >/etc/docker/daemon.json <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF
systemctl enable --now docker
systemctl restart docker

echo "[3/9] Creating the deploy user and installing the selected SSH public key"
if ! id "${DEPLOY_USER}" >/dev/null 2>&1; then
    useradd --create-home --shell /bin/bash "${DEPLOY_USER}"
fi
usermod -aG docker "${DEPLOY_USER}"

install -d -m 0700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
if ! grep -qxF "${DEPLOY_PUBLIC_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"; then
    printf '%s\n' "${DEPLOY_PUBLIC_KEY}" >>"/home/${DEPLOY_USER}/.ssh/authorized_keys"
fi
chown "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 0600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"

echo "[4/9] Hardening SSH without disabling SSH-key access"
cat >/etc/ssh/sshd_config.d/99-younz-hardening.conf <<'EOF'
PubkeyAuthentication yes
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
X11Forwarding no
EOF
sshd -t
systemctl reload ssh

echo "[5/9] Configuring firewall and SSH rate limiting"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

cat >/etc/fail2ban/jail.d/younz-sshd.local <<'EOF'
[sshd]
enabled = true
port = ssh
findtime = 10m
maxretry = 5
bantime = 1h
EOF
systemctl enable --now fail2ban
systemctl restart fail2ban

echo "[6/9] Adding a 2 GiB swap file when the server has no swap"
if ! swapon --show=NAME --noheadings | grep -q .; then
    if ! fallocate -l 2G /swapfile; then
        dd if=/dev/zero of=/swapfile bs=1M count=2048 status=progress
    fi
    chmod 0600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    if ! grep -qE '^/swapfile\s' /etc/fstab; then
        printf '%s\n' '/swapfile none swap sw 0 0' >>/etc/fstab
    fi
fi
cat >/etc/sysctl.d/99-younz-memory.conf <<'EOF'
vm.swappiness=10
vm.vfs_cache_pressure=50
EOF
sysctl --system >/dev/null

echo "[7/9] Preparing the application directory"
install -d -m 0750 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${APP_DIR}"

echo "[8/9] Configuring Nginx for the Next.js frontend on localhost:3000"
rm -f /etc/nginx/sites-enabled/default
cat >/etc/nginx/sites-available/younz-digital-center <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name younzdigitalcenter.my.id www.younzdigitalcenter.my.id;

    client_max_body_size 25m;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_connect_timeout 10s;
        proxy_read_timeout 180s;
        proxy_send_timeout 180s;
    }
}
EOF
ln -sfn /etc/nginx/sites-available/younz-digital-center /etc/nginx/sites-enabled/younz-digital-center
nginx -t
systemctl enable --now nginx
systemctl reload nginx

echo "[9/9] Enabling automatic security updates and writing deployment instructions"
cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
systemctl enable --now unattended-upgrades

cat >/root/YOUNZ_NEXT_STEPS.txt <<'EOF'
YOUNZ DIGITAL CENTER - NEXT STEPS

1. Point the domain's DNS A records to this UpCloud server IP.
2. Upload or clone the project into /opt/younz-digital-center.
3. Create .env from .env.production.example and fill every required secret.
   Never upload the laptop's existing .env through a public repository.
4. Keep the production Docker ports bound to 127.0.0.1 as defined in compose.yaml.
5. From the project directory, run:
      docker compose config
      docker compose up -d --build
      docker compose exec app php artisan migrate --force
      docker compose exec app php artisan storage:link
      docker compose exec app php artisan optimize:clear
6. Confirm containers and the local frontend:
      docker compose ps
      curl --fail http://127.0.0.1:3000/api/health
7. Configure HTTPS only after DNS points to this server. Do not expose PostgreSQL,
   Redis, Laravel port 8080, or the WhatsApp gateway directly to the internet.
8. For YOUNZ ERP, deploy the Laravel API so it listens only on 127.0.0.1:8001,
   create the erp.younzdigitalcenter.my.id DNS record, and install the ERP
   virtual-host block from deploy/nginx-younz.conf before issuing its certificate.

Initialization log: /var/log/younz-init.log
EOF
chmod 0600 /root/YOUNZ_NEXT_STEPS.txt

echo "Younz UpCloud initialization completed successfully."
echo "Log: /var/log/younz-init.log"
echo "Next steps: /root/YOUNZ_NEXT_STEPS.txt"
