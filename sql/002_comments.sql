-- 댓글(비회원) 테이블. 라이브 DB(weather_board)에 이미 생성되어 있는 스키마의 재현본.
-- 회원 인증 없음: 작성자 = nickname, 본인 수정/삭제 = password_hash(글 비밀번호).
CREATE TABLE IF NOT EXISTS comments (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nickname         VARCHAR(50)  NOT NULL                COMMENT '작성자 표시명(1~50자)',
  content          TEXT         NOT NULL                COMMENT '댓글 본문(앱에서 1~1000자 제한)',
  password_hash    VARCHAR(255) NOT NULL                COMMENT '글 비밀번호 해시(password_hash, 본인 수정/삭제용)',
  weather_snapshot VARCHAR(255) NULL     DEFAULT NULL   COMMENT '작성 시점 날씨 요약(선택)',
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성 시각',
  PRIMARY KEY (id),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
