FROM node:20-alpine AS frontend-build

WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY backend/ /var/www/html/
COPY --from=frontend-build /app/frontend/dist/ /var/www/html/

EXPOSE 80
