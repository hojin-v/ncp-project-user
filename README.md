# user-web (사용자) — web-user-01

일반 사용자 페이지: **현재 날씨 + 주간 예보 표시**, **댓글 보기/작성/수정/삭제**(닉네임+글비밀번호).
날씨는 공공데이터포털(data.go.kr) 기상청 API를 직접 호출. **CLOVA/LiteLLM 직접 접근 없음**
(LiteLLM은 web-admin-01 전용) → AI 날씨 코멘트는 admin-web이 생성·DB 저장한 것을 읽어 표시.

## 구조
```
src/    config.php(시크릿 로더) · db.php(PDO)
public/ 웹 docroot (→ /var/www/html 로 배포). health.php, index.php(메인), api/
bin/    healthcheck.php (CLI: DB 점검)
```

## 시크릿 (repo 밖)
배포 서버(web-user-01)의 `/var/www/weather-app-config/config.php` (db + weather 키).
litellm 키는 user-web에서 불필요.

## 점검
```
php bin/healthcheck.php   # [DB] OK
```

## 배포 (나중에)
이 repo의 `public/`(+ src/)를 web-user-01의 webroot로 이관. 소스코드만 옮김.
