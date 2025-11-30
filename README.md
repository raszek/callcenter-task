## Install project

### Running backend
```bash

cd docker

docker compose up -d 

docker compose exec app bash

## on container
php bin/console doctrine:database:create
php bin/console doctrine:database:create --env=test

php bin/console d:f:l
```

### Running frontend

```bash
## Create new terminal tab

cd frontend

npm install

npm run dev
```

## Running tests

```bash
docker compose exec app bash

php bin/phpunit
```
