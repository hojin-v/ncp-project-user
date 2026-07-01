<?php
declare(strict_types=1);
// 이 요청을 처리한 서버 식별(호스트명 + 사설 IP). 로드밸런서 분산을 새로고침으로 확인하기 위한 표시.
$serverHost = gethostname() ?: php_uname('n');
$serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
if ($serverAddr === '') {
    $resolved = @gethostbyname($serverHost);
    $serverAddr = ($resolved !== $serverHost) ? $resolved : 'unknown';
}
?><!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>날씨 & 댓글</title>
  <link rel="stylesheet" href="/assets/app.css">
  <script src="/assets/app.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <div class="server-badge" title="이 페이지를 응답한 서버입니다. 새로고침하면 로드밸런서가 다른 서버로 분산하는지 확인할 수 있습니다.">
      <span class="server-dot" aria-hidden="true"></span>
      served by <strong><?= htmlspecialchars($serverHost, ENT_QUOTES) ?></strong>
      <span class="server-ip"><?= htmlspecialchars($serverAddr, ENT_QUOTES) ?></span>
    </div>
    <header class="app-header">
      <h1><span aria-hidden="true">🌥️</span> 날씨 & 댓글</h1>
      <nav class="city-tabs" id="cityTabs" aria-label="도시 선택"></nav>
    </header>

    <main class="layout">
      <section class="weather-panel" aria-labelledby="weatherTitle">
        <div class="weather-card">
          <p class="location" id="weatherTitle">⌖ <span id="cityName">-</span></p>
          <div class="temperature-row">
            <div>
              <div class="temperature">
                <span id="temperature">--</span><small>°</small>
              </div>
              <p class="weather-summary" id="weatherSummary">-</p>
              <p class="weather-subline" id="weatherSubline">-</p>
            </div>
            <div class="weather-icon" id="weatherIcon" aria-hidden="true">⛅</div>
          </div>

          <div class="metric-grid">
            <div class="metric">
              <span>⌁ 습도</span>
              <strong id="humidity">-</strong>
            </div>
            <div class="metric">
              <span>↝ 풍속</span>
              <strong id="wind">-</strong>
            </div>
            <div class="metric">
              <span>☔ 강수</span>
              <strong id="rain">-</strong>
            </div>
            <div class="metric">
              <span>↟ 체감</span>
              <strong id="feelsLike">-</strong>
            </div>
          </div>
        </div>

        <div class="forecast-band">
          <div class="band-head">
            <p class="section-label">단기 예보</p>
            <span id="forecastCount">-</span>
          </div>
          <div class="forecast-list" id="forecastList"></div>
        </div>
      </section>

      <section class="comment-panel" aria-labelledby="commentTitle">
        <div class="comment-head">
          <div>
            <h2 id="commentTitle">댓글</h2>
          </div>
          <span class="count-pill" id="commentCount">0</span>
        </div>

        <form class="comment-form" id="commentForm">
          <div class="form-grid">
            <label>
              <span>닉네임</span>
              <input name="nickname" maxlength="50" autocomplete="nickname" placeholder="ex) 날씨요정" required>
            </label>
            <label>
              <span>비밀번호</span>
              <input name="password" type="password" minlength="4" autocomplete="new-password" placeholder="••••" required>
            </label>
          </div>
          <label>
            <span>내용</span>
            <textarea name="content" rows="4" maxlength="1000" placeholder="서울 날씨 어때요?" required></textarea>
          </label>
          <input type="hidden" name="weather_snapshot" id="weatherSnapshot">
          <div class="form-actions">
            <p class="form-status" id="formStatus" role="status"></p>
            <button type="submit">✈ 남기기</button>
          </div>
        </form>

        <div class="comment-list" id="commentList"></div>
      </section>
    </main>

    <footer class="app-footer">공공데이터 기반 날씨 · 댓글은 서버 DB에 저장됩니다</footer>
  </div>

  <template id="commentTemplate">
    <article class="comment-item">
      <div class="comment-meta">
        <strong class="comment-nickname"></strong>
        <span class="comment-date"></span>
      </div>
      <p class="comment-content"></p>
      <p class="comment-weather"></p>
      <div class="comment-controls">
        <button type="button" class="ghost edit-button">수정</button>
        <button type="button" class="ghost delete-button">삭제</button>
      </div>
      <form class="inline-form edit-form" hidden>
        <input name="nickname" maxlength="50" required>
        <input name="password" type="password" minlength="4" placeholder="비밀번호" required>
        <textarea name="content" rows="3" maxlength="1000" required></textarea>
        <div>
          <button type="submit">저장</button>
          <button type="button" class="ghost cancel-edit">취소</button>
        </div>
      </form>
      <form class="inline-form delete-form" hidden>
        <input name="password" type="password" minlength="4" placeholder="비밀번호" required>
        <div>
          <button type="submit">삭제</button>
          <button type="button" class="ghost cancel-delete">취소</button>
        </div>
      </form>
    </article>
  </template>
</body>
</html>
