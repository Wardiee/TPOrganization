# Deploying Tropang Potchi to Oracle Cloud (Always Free)

This guide puts the app online at an **https://** address, on a free Oracle Cloud server, so it works on any phone and installs as an app. Follow the steps in order. Every command can be copied and pasted.

**What you'll end up with**

- A small Linux server (Ubuntu 24.04) on Oracle's free tier, running the app, the database and your private files.
- A free web address such as `tropangpotchi.duckdns.org` with HTTPS (free certificate, renews itself).
- The 06:00 daily task sync running by itself.
- Nightly backups of the database and uploaded files.
- The code kept in a **private** GitHub repository, so updating the server is one command.

**Time needed:** about 1–2 hours the first time.

> **Privacy reminder.** The app holds records of minors. The GitHub repository must stay **private** (the seed files contain real names and birthdays). Never paste your `.env` file anywhere.

---

## Contents

1. [Before you start](#1-before-you-start)
2. [Put the code in a private GitHub repository](#2-put-the-code-in-a-private-github-repository)
3. [Create the Oracle server](#3-create-the-oracle-server)
4. [Get a free web address (DuckDNS)](#4-get-a-free-web-address-duckdns)
5. [Connect to the server](#5-connect-to-the-server)
6. [Open the firewall](#6-open-the-firewall)
7. [Install PHP, the database, Nginx, Composer and Node](#7-install-php-the-database-nginx-composer-and-node)
8. [Create the database](#8-create-the-database)
9. [Download and set up the app](#9-download-and-set-up-the-app)
10. [Bring your data across (or start fresh)](#10-bring-your-data-across-or-start-fresh)
11. [Set up Nginx and HTTPS](#11-set-up-nginx-and-https)
12. [Turn on the daily task sync (scheduler)](#12-turn-on-the-daily-task-sync-scheduler)
13. [Backups](#13-backups)
14. [First sign-in and the admin password](#14-first-sign-in-and-the-admin-password)
15. [Install the app on phones](#15-install-the-app-on-phones)
16. [Updating the app later](#16-updating-the-app-later)
17. [Security checklist](#17-security-checklist)
18. [Troubleshooting](#18-troubleshooting)

---

## 1. Before you start

You need:

- [ ] An **Oracle Cloud** account (cloud.oracle.com). Sign-up asks for a credit card to confirm your identity; the Always Free resources are not charged.
- [ ] A **GitHub** account (github.com).
- [ ] A **DuckDNS** account (duckdns.org, sign in with Google or GitHub). This gives a free web address.
- [ ] Your laptop with this project, XAMPP's MariaDB (if you're bringing your data across) and Git.

Oracle's free tier rules change from time to time, and in 2026 Oracle reduced the free Ampere allowance to 2 CPUs and 12 GB of memory, which is still plenty for this app. Check Oracle's current "Always Free" page when you sign up, and **keep off-server backups** (step 13) in case anything changes.

---

## 2. Put the code in a private GitHub repository

Done already: the code is in the private repository `Wardiee/TPOrganization` (branch `main`). To send new changes from your laptop later, run `git push` in the project folder.

If you ever set this up again from scratch: create a **Private** repository on github.com without a README, then in the project folder run `git remote add origin <repo address>`, `git branch -M main` and `git push -u origin main`.

Your `.env`, uploaded files (`storage/app/private`) and `node_modules` are excluded automatically by `.gitignore`.

---

## 3. Create the Oracle server

In the Oracle Cloud console:

1. Open **Compute → Instances → Create instance**.
2. **Name:** `tropang-potchi`.
3. **Image:** click *Change image* → **Canonical Ubuntu 24.04**.
4. **Shape:** click *Change shape* → **Ampere** → `VM.Standard.A1.Flex`, with **1 OCPU and 6 GB memory** (or up to 2 OCPUs / 12 GB; this app needs very little).
   If Ampere capacity isn't available in your region, the x86 `VM.Standard.E2.1.Micro` (also Always Free) works too, just slower.
5. **Networking:** keep the default new virtual cloud network, and make sure **Assign a public IPv4 address** is on.
6. **SSH keys:** choose **Generate a key pair for me** and click **Save private key**. Keep that file safe: it's the only way into the server.
7. Click **Create**. When it's running, copy the **Public IP address** (e.g. `140.238.x.x`).

**Open ports 80 and 443 in Oracle's network** (needed for the website):

1. On the instance page, click the **Subnet** link → the **Default Security List** → **Add Ingress Rules**.
2. Add one rule: Source CIDR `0.0.0.0/0`, IP Protocol **TCP**, Destination Port Range `80,443`. Save.

---

## 4. Get a free web address (DuckDNS)

1. Sign in at duckdns.org.
2. Type a subdomain, e.g. `tropangpotchi`, and click **add domain**.
3. In the **current ip** box next to it, paste the server's public IP and click **update ip**.

Your address is now `tropangpotchi.duckdns.org` (use your own name everywhere this guide says `YOUR-DOMAIN`).

You can use your own domain instead (about $10/year). Point its **A record** to the server IP.

---

## 5. Connect to the server

On Windows, open **PowerShell** (or Git Bash) on your laptop:

```bash
ssh -i path\to\ssh-key-2026-xx-xx.key ubuntu@YOUR-SERVER-IP
```

The first time, type `yes` to trust the server. If Windows complains the key file is "too open", right-click the key file → Properties → Security → Advanced → disable inheritance and leave only your own user with Read access.

Everything from here until step 14 runs **on the server**, inside this SSH window.

---

## 6. Open the firewall

Oracle's Ubuntu images have their own firewall that blocks websites even after step 3. Run:

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

---

## 7. Install PHP, the database, Nginx, Composer and Node

The app needs **PHP 8.4**. Ubuntu 24.04 ships an older PHP, so add the widely used PHP package source first:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common unzip git curl
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y nginx mariadb-server \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath
```

**Composer** (PHP package manager):

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Node.js 22** (only used to build the styles):

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

**Allow 20 MB uploads** (photos and documents):

```bash
sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 21M/; s/^post_max_size.*/post_max_size = 25M/' /etc/php/8.4/fpm/php.ini
sudo systemctl restart php8.4-fpm
```

**Automatic security updates:**

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades   # choose "Yes"
```

---

## 8. Create the database

Secure MariaDB (press Enter for the current root password, then answer **Y** to everything and set a strong root password):

```bash
sudo mariadb-secure-installation
```

Create the app's database and its own user. Replace `CHOOSE-A-STRONG-PASSWORD` with a long password and **write it down**; you need it in step 9.

```bash
sudo mariadb -e "CREATE DATABASE tropang_potchi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tropang'@'localhost' IDENTIFIED BY 'CHOOSE-A-STRONG-PASSWORD';
GRANT ALL PRIVILEGES ON tropang_potchi.* TO 'tropang'@'localhost';
FLUSH PRIVILEGES;"
```

---

## 9. Download and set up the app

**Get the code.** Because the repository is private, GitHub needs a token:

1. On github.com: profile picture → **Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**.
2. Repository access: **Only select repositories** → `TPOrganization`. Permissions: **Contents: Read-only**. Generate and copy the token.

On the server:

```bash
sudo mkdir -p /var/www && sudo chown ubuntu:ubuntu /var/www
cd /var/www
git clone https://github.com/Wardiee/TPOrganization.git tropang-potchi
# Username: your GitHub username. Password: paste the token.
cd tropang-potchi
git config credential.helper store   # remembers the token for future updates
```

**Create the settings file:**

```bash
cp .env.example .env
nano .env
```

Change these lines (use the arrow keys; save with **Ctrl+O, Enter**, exit with **Ctrl+X**):

```ini
APP_NAME="Tropang Potchi"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tropang_potchi
DB_USERNAME=tropang
DB_PASSWORD=CHOOSE-A-STRONG-PASSWORD

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
LOG_LEVEL=warning

# Only needed if you start fresh (step 10, option B):
ADMIN_NAME="Your Name"
ADMIN_EMAIL=your@email.com
ADMIN_PASSWORD=a-temporary-strong-password
```

(`DB_HOST=127.0.0.1` etc. replace the commented `# DB_...` lines that are there by default.)

**Install and build:**

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci
npm run build
```

**Let the web server write to storage and cache:**

```bash
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

---

## 10. Bring your data across (or start fresh)

### Option A: move your current data from XAMPP (recommended)

**On your laptop:**

1. In phpMyAdmin (http://localhost/phpmyadmin), click the `tropang_potchi` database → **Export** → Format **SQL** → **Export**. You get `tropang_potchi.sql`.
2. Copy the database file and your uploaded files to the server (PowerShell, in the project folder):

   ```bash
   scp -i path\to\key tropang_potchi.sql ubuntu@YOUR-SERVER-IP:/tmp/
   scp -i path\to\key -r storage\app\private\documents ubuntu@YOUR-SERVER-IP:/tmp/documents
   ```

**On the server:**

```bash
cd /var/www/tropang-potchi
mariadb -u tropang -p tropang_potchi < /tmp/tropang_potchi.sql      # enter the DB password
php artisan migrate --force                                         # adds anything newer; safe to run
mkdir -p storage/app/private/documents
cp -r /tmp/documents/. storage/app/private/documents/
sudo chown -R ubuntu:www-data storage && sudo chmod -R ug+rwX storage
rm /tmp/tropang_potchi.sql && rm -r /tmp/documents
```

Your login (email and password) comes across with the data. You can remove the three `ADMIN_` lines from `.env`.

### Option B: start fresh from the founder's original records

```bash
php artisan migrate --force
php artisan db:seed --force
```

This creates the admin from the `ADMIN_` lines in `.env`, the 42 members, activities, attendance, the 2024 org chart and the tasks. After your first sign-in, change the password (step 14) and **delete the `ADMIN_PASSWORD` line** from `.env`.

> Never run `migrate:fresh` or `db:seed` again once real data is in. They would wipe or duplicate records.

**Speed things up** (run after any `.env` change too):

```bash
php artisan optimize
```

---

## 11. Set up Nginx and HTTPS

Create the site configuration:

```bash
sudo nano /etc/nginx/sites-available/tropang-potchi
```

Paste this (replace `YOUR-DOMAIN`), then save and exit:

```nginx
server {
    listen 80;
    server_name YOUR-DOMAIN;
    root /var/www/tropang-potchi/public;
    index index.php;

    client_max_body_size 25M;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # The installable app's manifest and service worker
    location = /manifest.webmanifest { default_type application/manifest+json; }
    location = /sw.js { add_header Cache-Control "no-cache"; }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Never serve hidden files (.env, .git, …)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Turn it on:

```bash
sudo ln -s /etc/nginx/sites-available/tropang-potchi /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Visit `http://YOUR-DOMAIN`: you should see the sign-in page.

**Add HTTPS (free certificate from Let's Encrypt):**

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d YOUR-DOMAIN
```

Enter your email, agree to the terms, and choose to **redirect** HTTP to HTTPS. The certificate renews itself automatically. Now open `https://YOUR-DOMAIN`.

---

## 12. Turn on the daily task sync (scheduler)

Laravel's scheduler runs `tasks:sync` every day at 06:00 Manila time. It needs one entry that checks every minute:

```bash
crontab -e   # choose nano if asked
```

Add this line at the bottom, save and exit:

```cron
* * * * * cd /var/www/tropang-potchi && php artisan schedule:run >> /dev/null 2>&1
```

Check it's registered:

```bash
php artisan schedule:list   # should show "0 6 * * *  php artisan tasks:sync"
```

---

## 13. Backups

Two things need backing up: the **database** and the **uploaded files** (`storage/app/private`). The script below saves both every night and keeps 14 days.

**Save the database password for backups** (replace the password):

```bash
cat > ~/.my.cnf <<'EOF'
[client]
user=tropang
password=CHOOSE-A-STRONG-PASSWORD
EOF
chmod 600 ~/.my.cnf
```

**Create the backup script:**

```bash
mkdir -p ~/backups
cat > ~/backup.sh <<'EOF'
#!/bin/bash
set -e
STAMP=$(date +%F)
mariadb-dump --single-transaction tropang_potchi | gzip > ~/backups/db-$STAMP.sql.gz
tar -czf ~/backups/files-$STAMP.tar.gz -C /var/www/tropang-potchi/storage/app private
find ~/backups -type f -mtime +14 -delete
EOF
chmod +x ~/backup.sh
~/backup.sh && ls -lh ~/backups      # test it once
```

**Run it nightly at 01:00** (`crontab -e`, add):

```cron
0 1 * * * /home/ubuntu/backup.sh >> /home/ubuntu/backups/backup.log 2>&1
```

**Keep a copy off the server.** If the server is lost, backups on it are lost too. At least once a week, download the newest files to your laptop (PowerShell):

```bash
scp -i path\to\key "ubuntu@YOUR-SERVER-IP:~/backups/*" D:\TropangPotchi-Backups\
```

Store that folder somewhere safe (an external drive or a private cloud folder). It contains personal data of minors.

**Restoring** (only if something goes wrong):

```bash
gunzip -c ~/backups/db-2026-12-01.sql.gz | mariadb tropang_potchi
tar -xzf ~/backups/files-2026-12-01.tar.gz -C /var/www/tropang-potchi/storage/app
sudo chown -R ubuntu:www-data /var/www/tropang-potchi/storage
```

---

## 14. First sign-in and the admin password

1. Open `https://YOUR-DOMAIN` and sign in.
2. Go to **Account settings → Security** (on phones: **More → Account settings**) and change the password if you used a temporary one. Consider turning on **two-factor login** there too.
3. On the server, remove the `ADMIN_PASSWORD` line from `.env`, then run `php artisan optimize`.

**Forgot your password?** The app doesn't send email yet, so "Forgot your password?" can't email a link. Reset it on the server instead:

```bash
cd /var/www/tropang-potchi
php artisan tinker --execute '$u = App\Models\User::first(); $u->password = "NEW-STRONG-PASSWORD"; $u->save(); echo "done";'
```

(To make "Forgot your password?" work, add your email provider's SMTP settings to the `MAIL_` lines in `.env`.)

---

## 15. Install the app on phones

Now that the address is **https://**, phones install it as a real app (full screen, no address bar):

- **Android (Chrome):** open `https://YOUR-DOMAIN`, sign in, then **⋮ → Install app**.
- **iPhone (Safari):** open the address, sign in, then **Share → Add to Home Screen → Add**.

Remove any test install from before first (long-press the icon → Remove), and set the Chrome flag you used for testing back to **Default**.

---

## 16. Updating the app later

When there are new changes on your laptop:

**On your laptop:**

```bash
git push
```

**On the server:**

```bash
cd /var/www/tropang-potchi
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
sudo systemctl reload php8.4-fpm
```

Run a backup first (`~/backup.sh`) if the update changes the database. Installed phone apps pick up the new version automatically the next time they open.

---

## 17. Security checklist

- [ ] GitHub repository is **Private**.
- [ ] `.env` has `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] `ADMIN_PASSWORD` removed from `.env` after the first sign-in.
- [ ] The site only opens on **https://** (certbot redirect on).
- [ ] Oracle security list only allows ports **22, 80, 443**.
- [ ] Database user `tropang` has a strong password; MariaDB root has a strong password.
- [ ] Nightly backups running, and a weekly copy kept off the server.
- [ ] Automatic security updates on (`unattended-upgrades`).
- [ ] Keep the SSH key file private; don't share it.

---

## 18. Troubleshooting

| Problem | Try this |
|---|---|
| Site doesn't load at all | Check Oracle's security list allows 80/443 (step 3) **and** the server firewall (step 6). Check DuckDNS has the right IP. |
| "502 Bad Gateway" | PHP isn't running: `sudo systemctl restart php8.4-fpm`. |
| "500 Server Error" | See the last error: `tail -n 50 /var/www/tropang-potchi/storage/logs/laravel.log`. Often a `.env` typo (then `php artisan optimize`) or folder permissions (step 9). |
| Page has no colours/styles | Run `npm ci && npm run build`, and make sure there's no `public/hot` file (`rm public/hot`). |
| Uploads over 2 MB fail | Re-check the PHP upload limits (step 7) and `client_max_body_size 25M` (step 11), then restart PHP and Nginx. |
| Tasks don't update at 06:00 | Check the cron line (step 12) and run `php artisan schedule:list`. Run `php artisan tasks:sync` once by hand to test. |
| Can't install on the phone | The address must start with **https://**. Open it in Chrome (Android) or Safari (iPhone). |
| Changed `.env` but nothing happened | Run `php artisan optimize` after every `.env` change. |
