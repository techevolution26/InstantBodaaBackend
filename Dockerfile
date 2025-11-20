# Dockerfile — php-fpm + nginx (multi-stage)
FROM php:8.3-fpm-bookworm AS base

# System packages and build deps (for extensions + composer)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    ca-certificates curl git unzip zip libzip-dev libonig-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev build-essential pkg-config \
    iproute2 procps \
    && rm -rf /var/lib/apt/lists/*


# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mbstring exif xml zip gd \
    && pecl channel-update pecl.php.net || true

# Install Composer (official)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first to leverage cache
COPY composer.json composer.lock ./

# Install PHP deps (no dev in production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Node build stage
FROM node:22-bullseye-slim AS nodebuilder
WORKDIR /var/www/html

# copy package files and install (fallback if no lockfile)
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci --legacy-peer-deps; else npm install --legacy-peer-deps; fi

# copy app and build assets
COPY . .
RUN npm run build || true

# Final image: php-fpm + nginx
FROM base AS final

# install nginx and gettext (envsubst available via gettext-base)
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx gettext-base \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# copy php vendor from base stage
COPY --from=base /var/www/html/vendor ./vendor

# copy built frontend artifacts from nodebuilder
COPY --from=nodebuilder /var/www/html/public ./public
COPY --from=nodebuilder /var/www/html/node_modules ./node_modules

# copy app code
COPY . .

# place nginx config template (we'll envsubst $PORT at startup)
RUN mkdir -p /etc/nginx/conf.d
COPY docker/nginx/default.conf.template /etc/nginx/conf.d/default.conf.template

# make start script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# ensure storage directories exist and are writable
RUN mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R ug+rw storage bootstrap/cache

# expose HTTP port (Railway will use it)
EXPOSE 80

# start script will run php-fpm (daemonize) then start nginx in foreground
CMD ["/start.sh"]
