<?php
// LB 헬스체크용. 단순 200 응답(외부 의존성 없음 — 인스턴스 생존만 확인).
http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
