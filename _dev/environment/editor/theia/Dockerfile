FROM ghcr.io/eclipse-theia/theia-ide/theia-ide:1.69.0

#obviously not the best permissions if this image was to ever be published
#however we are not going to publish this image rather we 
#give the agent root to enable work to be 
#conducted in the container 
USER root 

SHELL ["/bin/bash", "-c"]

WORKDIR /

#Install php and composer on on Debian 11 (Bullseye)
RUN apt update && apt upgrade -y \
    && apt install -y apt-transport-https lsb-release ca-certificates curl gnupg2 build-essential wget unzip tree sqlite3 nano\ 
    && curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-php.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/sury-php.list \ 
    && apt update \
    && apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-zip php8.4-xml php8.4-gd php8.4-curl php8.4-mbstring php8.4-sqlite3 php8.4-bcmath php8.4-soap php8.4-readline php8.4-intl php8.4-common \ 
    && curl -sS https://getcomposer.org/installer -o composer-setup.php \ 
    && HASH=`curl -sS https://composer.github.io/installer.sig` \ 
    && php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \ 
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \ 
    && composer global require laravel/installer

#
## Install Claude Code globally via official installer
RUN curl -fsSL https://claude.ai/install.sh | bash
RUN echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc && . ~/.bashrc

#Clean up
#RUN apt-get clean autoclean \
#    && apt-get autoremove --yes \
#    && rm -rf /var/lib/{apt,dpkg,cache,log}/
#
## Install Opencode
#RUN npm install -g opencode-ai
#
## Install OpenAI Codex CLI
#RUN npm install -g @openai/codex
#
## Install Gemini CLI
#RUN npm install -g @google/gemini-cli
#
## Install GitHub Copilot CLI
#RUN npm install -g @github/copilot