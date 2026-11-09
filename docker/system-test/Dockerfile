FROM php:8.4-cli-bookworm@sha256:5d3eebbd478bc102494c539d43be98a885984b33f929c6081f1c59033a878d6a

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" intl mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
