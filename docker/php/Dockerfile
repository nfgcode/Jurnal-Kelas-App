FROM php:8.3-fpm-alpine

# Arguments for user mapping (optional)
ARG USER_ID=1000
ARG GROUP_ID=1000

# Install system dependencies (Alpine packages)
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    icu-dev \
    zip \
    unzip \
    supervisor \
    bash \
    gcompat \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Bun (lightweight JS runtime, replaces Node.js + npm)
RUN curl -fsSL https://bun.sh/install | bash \
    && mv /root/.bun/bin/bun /usr/local/bin/bun \
    && ln -s /usr/local/bin/bun /usr/local/bin/bunx \
    && rm -rf /root/.bun

# Create system user
RUN addgroup -g ${GROUP_ID} appuser \
    && adduser -u ${USER_ID} -G appuser -D appuser \
    && mkdir -p /home/appuser/.composer \
    && chown -R appuser:appuser /home/appuser

# Set working directory
WORKDIR /var/www/html

# Copy supervisor config
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create supervisor log directory (writable by appuser) and Laravel
# storage framework directories that must exist before artisan runs.
RUN mkdir -p /var/log/supervisor \
    && chown -R appuser:appuser /var/log/supervisor \
    && mkdir -p /var/run \
    && chmod 1777 /var/run

# Switch to non-root user
USER appuser

EXPOSE 9000

# Use supervisord so php-fpm, the queue worker, and the scheduler all run
# inside the same container (see supervisord.conf for the program list).
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
