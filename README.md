# ncp-project-user — user-web (web-user-01)

일반 사용자 페이지: **현재 날씨 + 주간 예보 표시**, **댓글 보기/작성/수정/삭제**(닉네임+글비밀번호).
날씨는 공공데이터포털(data.go.kr) 기상청 API를 직접 호출. **CLOVA/LiteLLM 직접 접근 없음**
(LiteLLM은 web-admin-01 전용 개발 보조 도구. 서비스 런타임에서 호출하지 않음.)

## 먼저 읽기

배포/운영 절차는 `DEPLOY.md` 참조.

## 구조
```
src/    config.php(시크릿 로더) · db.php(PDO) · weather.php(data.go.kr API/DB 저장) · comments.php(댓글 CRUD)
public/ 웹 docroot (→ /var/www/html 로 배포). index.php(메인), health.php, api/, assets/
bin/    healthcheck.php (CLI: DB 점검) · fetch_weather.php(날씨 수집) · comments_smoke.php(댓글 CRUD 검증)
sql/    DB 테이블 DDL
```

## 시크릿 (repo 밖)
배포 서버(web-user-01)의 `/var/www/weather-app-config/config.php` (db + weather 키).
litellm 키는 user-web에서 불필요.
템플릿은 `weather-app-config.template.php`.

## 점검
```
php bin/healthcheck.php   # [DB] OK
php bin/fetch_weather.php # 서울/부산/제주 날씨 API 호출→DB 저장
php bin/comments_smoke.php # 댓글 작성→조회→수정→삭제 라운드트립
php -S 127.0.0.1:18080 -t public # 로컬 화면 확인
```

## 배포

```bash
sudo bash deploy-user-web.sh
```

자세한 절차는 `DEPLOY.md` 참조.
