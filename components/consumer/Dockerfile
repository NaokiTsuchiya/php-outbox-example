FROM php:8.3-cli

WORKDIR /app
COPY app/public/index.php public/index.php

EXPOSE 8081
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public", "public/index.php"]
