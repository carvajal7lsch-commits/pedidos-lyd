FROM php:8.2-apache

# Instalar dependencias del sistema y extensiones de PHP requeridas
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli gd zip pdo pdo_mysql

# Habilitar mod_rewrite de Apache para manejo de rutas si se requiere
RUN a2enmod rewrite

# Ajustar ServerName para evitar advertencias de Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar el código fuente de la aplicación
COPY . /var/www/html/

# Asegurar permisos correctos para la carpeta de cargas y archivos
RUN mkdir -p /var/www/html/public/uploads/productos \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/uploads

# Exponer el puerto interno de Apache (80)
EXPOSE 80
