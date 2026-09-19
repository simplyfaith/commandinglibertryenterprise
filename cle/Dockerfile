# Use the Debian Node image for Vite/esbuild. The Alpine build was failing
# during `npm ci` on Render while installing its platform-specific binary.
FROM node:20-bookworm-slim AS frontend-build

WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
# Copy only application sources. The repository contains Windows node_modules;
# copying them here would replace the Linux dependencies installed above and
# makes Vite/esbuild fail during Render's build.
COPY frontend/index.html ./
COPY frontend/vite.config.js ./
COPY frontend/src ./src
COPY frontend/public ./public
RUN npm run build

FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY backend/ /var/www/html/
COPY --from=frontend-build /app/frontend/dist/ /var/www/html/

# Apache runs PHP as www-data. Create a writable upload directory during the
# image build because the source upload directory is deliberately gitignored.
# Without this, admin image uploads fail after deployment with a permissions
# error.
RUN mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod 775 /var/www/html/storage /var/www/html/storage/uploads

# Keep the PHP limits aligned with the application's 5 MB upload limit.
COPY php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80
