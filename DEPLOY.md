# DEPLOY.md — web-user-01 배포

## 1. 서버 전제

|항목|값|
|---|---|
|서버|`web-user-01`|
|웹서버|Apache|
|언어|PHP|
|DB|NCP Cloud DB for MySQL `weather_board`|
|문서 루트|`/var/www/html`|
|소스 위치|`/var/www/src`, `/var/www/bin`|
|시크릿|`/var/www/weather-app-config/config.php`|

필요 패키지:

```bash
sudo apt-get update
sudo apt-get install -y apache2 php php-mysql mysql-client curl rsync git
sudo systemctl enable --now apache2
```

## 2. 소스 받기

```bash
cd /home/hojin
git clone git@github.com:hojin-v/ncp-project-user.git
cd ncp-project-user
```

SSH 키가 없다면 HTTPS clone 후 GitHub 인증을 설정한다.

## 3. 시크릿 설정

repo의 템플릿을 webroot 밖으로 복사한다.

```bash
sudo mkdir -p /var/www/weather-app-config
sudo cp weather-app-config.template.php /var/www/weather-app-config/config.php
sudo nano /var/www/weather-app-config/config.php
sudo chown root:www-data /var/www/weather-app-config/config.php
sudo chmod 640 /var/www/weather-app-config/config.php
```

채울 값:

|키|설명|
|---|---|
|`db.host`|Cloud DB for MySQL 사설 엔드포인트(콘솔에서 확인)|
|`db.password`|`weather_user` DB 비밀번호|
|`weather.service_key`|data.go.kr 기상청 단기예보 서비스키|

`db.user`/`db.database`/`db.charset`은 템플릿 기본값을 유지하고, `db.host`는 실제 사설 엔드포인트로 채운다.

## 4. 배포

```bash
sudo bash deploy-user-web.sh
```

스크립트가 하는 일:

|단계|내용|
|---|---|
|1|PHP 문법 검사|
|2|기존 `/var/www/html`, `/var/www/src`, `/var/www/bin` 백업|
|3|`public/`→`/var/www/html`, `src/`→`/var/www/src`, `bin/`→`/var/www/bin` 배포|
|4|localhost health/API 확인|

백업 위치:

```text
/var/www/backups/user-web-YYYYmmdd-HHMMSS
```

## 5. 검증

```bash
curl -i http://127.0.0.1/health.php
curl -i 'http://127.0.0.1/api/weather.php?city=seoul'
curl -i http://127.0.0.1/api/comments.php
php /var/www/bin/healthcheck.php
```

날씨 데이터 갱신:

```bash
php /var/www/bin/fetch_weather.php
```

댓글 라운드트립 테스트:

```bash
php /var/www/bin/comments_smoke.php
```

## 6. cron 선택

날씨를 주기적으로 갱신하려면:

```bash
sudo crontab -e
```

예시:

```cron
*/30 * * * * /usr/bin/php /var/www/bin/fetch_weather.php >> /var/log/weather-fetch.log 2>&1
```

## 7. 문제 확인

|증상|확인|
|---|---|
|`config not readable`|`/var/www/weather-app-config/config.php` 존재/권한 확인|
|DB 접속 실패|DB ACG가 `acg-user-web`(user 서버 ACG) 3306 허용인지 확인|
|날씨 API 실패|서비스키, NAT outbound 443, data.go.kr 호출 제한 확인|
|ALB 502/헬스 실패|`/health.php` 200, Target Group health check path 확인|

