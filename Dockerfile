FROM php:8.2-cli-alpine

RUN apk add --no-cache curl

WORKDIR /app

COPY . /app

RUN mkdir -p /app/data && chmod -R 777 /app/data

ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} bot.php"]
