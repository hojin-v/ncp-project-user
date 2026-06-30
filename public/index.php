<?php
declare(strict_types=1);
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
    <header class="app-header">
      <div>
        <p class="eyebrow">Weather Board</p>
        <h1>날씨 & 댓글</h1>
      </div>
      <nav class="city-tabs" id="cityTabs" aria-label="도시 선택"></nav>
    </header>

    <main class="layout">
      <section class="weather-panel" aria-labelledby="weatherTitle">
        <div class="weather-card">
          <div class="weather-main">
            <div>
              <p class="section-label" id="weatherTitle">현재 날씨</p>
              <h2 id="cityName">-</h2>
              <p class="weather-summary" id="weatherSummary">-</p>
            </div>
            <div class="temperature">
              <span id="temperature">--</span><small>°C</small>
            </div>
          </div>

          <div class="metric-grid">
            <div class="metric">
              <span>습도</span>
              <strong id="humidity">-</strong>
            </div>
            <div class="metric">
              <span>풍속</span>
              <strong id="wind">-</strong>
            </div>
            <div class="metric">
              <span>강수</span>
              <strong id="rain">-</strong>
            </div>
            <div class="metric">
              <span>기준</span>
              <strong id="observedAt">-</strong>
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
            <p class="section-label">댓글</p>
            <h2 id="commentTitle">오늘의 기록</h2>
          </div>
          <span class="count-pill" id="commentCount">0</span>
        </div>

        <form class="comment-form" id="commentForm">
          <div class="form-grid">
            <label>
              <span>닉네임</span>
              <input name="nickname" maxlength="50" autocomplete="nickname" required>
            </label>
            <label>
              <span>비밀번호</span>
              <input name="password" type="password" minlength="4" autocomplete="new-password" required>
            </label>
          </div>
          <label>
            <span>내용</span>
            <textarea name="content" rows="4" maxlength="1000" required></textarea>
          </label>
          <input type="hidden" name="weather_snapshot" id="weatherSnapshot">
          <div class="form-actions">
            <p class="form-status" id="formStatus" role="status"></p>
            <button type="submit">남기기</button>
          </div>
        </form>

        <div class="comment-list" id="commentList"></div>
      </section>
    </main>
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
