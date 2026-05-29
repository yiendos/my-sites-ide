FROM ghcr.io/eclipse-theia/theia-ide/theia-ide:1.69.0 AS aistudio

USER root

SHELL ["/bin/bash", "-c"]

# System-level installs (need root): PHP 8.4 + Composer binary at /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    apt-transport-https lsb-release ca-certificates curl gnupg2 \
    build-essential wget unzip tree sqlite3 nano \
    && curl -fsSL https://packages.sury.org/php/apt.gpg \
    | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-php.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
    | tee /etc/apt/sources.list.d/sury-php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-zip php8.4-xml \
    php8.4-gd php8.4-curl php8.4-mbstring php8.4-sqlite3 php8.4-bcmath \
    php8.4-soap php8.4-readline php8.4-intl php8.4-common \
    && HASH=$(curl -sS https://composer.github.io/installer.sig) \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php -r "if (hash_file('SHA384', '/tmp/composer-setup.php') !== '$HASH') { echo 'Installer corrupt'; unlink('/tmp/composer-setup.php'); exit(1); } echo 'Installer verified';" \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php \
    && rm -rf /var/lib/apt/lists/*

# Switch to theia for user-level installs so paths land under /home/theia
USER theia

# Laravel installer → /home/theia/.config/composer/vendor/bin
RUN composer global require laravel/installer

# Claude Code CLI → /home/theia/.local/bin
RUN curl -fsSL https://claude.ai/install.sh | bash

# PATH references theia's home directories; no ENV HOME hack needed
ENV PATH="/home/theia/.local/bin:/home/theia/.config/composer/vendor/bin:${PATH}"

# Copy plugins — theia already owns /home/theia/plugins in the base image
COPY --chown=theia:theia plugins/ /home/theia/plugins/

# Uncomment to add further AI CLI tools at build time:
# RUN npm install -g opencode-ai
# RUN npm install -g @openai/codex
# RUN npm install -g @google/gemini-cli
