# ==========================================
# Stage 1: Build Dependencies via Composer
# ==========================================
FROM composer:2.10 AS builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ==========================================
# Stage 2: Hardened Runtime Container
# ==========================================
FROM php:8.3-cli-alpine AS runtime

LABEL maintainer="Sanket Kale <contact@sanketkale.com>"
LABEL org.opencontainers.image.title="php-api-foundation"
LABEL org.opencontainers.image.description="Dependency-light PHP 8.3 REST API foundation"
LABEL org.opencontainers.image.source="https://github.com/thesanketkale/php-api-foundation"
LABEL org.opencontainers.image.licenses="MIT"

# Install production PHP extensions
RUN apk add --no-cache \
    curl \
    fcgi \
    icu-data-full \
    libzip-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    opcache

# Security & hardening: non-root user
RUN addgroup -g 1000 appgroup && \
    adduser -u 1000 -G appgroup -s /bin/sh -D appuser

WORKDIR /var/www/html

# Copy application source & vendor from builder
COPY --from=builder --chown=appuser:appgroup /app/vendor /var/www/html/vendor
COPY --chown=appuser:appgroup . /var/www/html

# Create runtime storage directories with appropriate permissions
RUN mkdir -p /var/www/html/storage/logs \
             /var/www/html/storage/cache/ratelimit \
             /tmp/app \
    && chown -R appuser:appgroup /var/www/html/storage /tmp/app \
    && chmod -R 775 /var/www/html/storage /tmp/app

# Hardened PHP runtime configuration
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_startup_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "memory_limit = 128M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/security.ini

USER appuser

EXPOSE 8080

# Health check probe
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/v1/health || exit 1

ENTRYPOINT ["php", "-S", "0.0.0.0:8080", "-t", "public"]
