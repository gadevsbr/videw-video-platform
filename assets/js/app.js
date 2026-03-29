import { createApp, computed, effect, html, signal } from "@bragamateus/gui";

const bootstrap = window.__VIDEW__ ?? {};
const baseUrl = bootstrap.baseUrl ?? "";
const exitUrl = bootstrap.exitUrl ?? "https://www.google.com";
const initialVideos = Array.isArray(bootstrap.videos) ? bootstrap.videos : [];
const numberFormatter = new Intl.NumberFormat("en-US");

function watchUrl(slug) {
  return `${baseUrl}/watch.php?slug=${encodeURIComponent(slug)}`;
}

function sortVideos(videos, sortMode) {
  const collection = [...videos];

  if (sortMode === "duration") {
    return collection.sort((left, right) => right.duration_minutes - left.duration_minutes);
  }

  if (sortMode === "title") {
    return collection.sort((left, right) => left.title.localeCompare(right.title, "en"));
  }

  return collection.sort((left, right) => {
    const leftTime = Date.parse(left.published_at ?? "") || 0;
    const rightTime = Date.parse(right.published_at ?? "") || 0;
    return rightTime - leftTime;
  });
}

function categoryChip(label, currentCategory) {
  const chipLabel = label === "all" ? "All" : label;

  return html`
    <button
      class=${() => currentCategory.value === label ? "chip chip--active" : "chip"}
      type="button"
      on:click=${() => {
        currentCategory.value = label;
      }}
    >${chipLabel}</button>
  `;
}

function accessToggle(mode, label, access) {
  return html`
    <button
      class=${() => access.value === mode ? "toggle-button toggle-button--active" : "toggle-button"}
      type="button"
      on:click=${() => {
        access.value = mode;
      }}
    >${label}</button>
  `;
}

function sortToggle(mode, label, sort) {
  return html`
    <button
      class=${() => sort.value === mode ? "sort-button sort-button--active" : "sort-button"}
      type="button"
      on:click=${() => {
        sort.value = mode;
      }}
    >${label}</button>
  `;
}

function renderVideoCard(video) {
  const posterUrl = video.resolved_listing_poster_url ?? video.listing_poster_url ?? video.resolved_poster_url ?? video.poster_url;
  const href = watchUrl(video.slug);

  return html`
    <article class="video-card">
      <a class="video-card__media" href=${href}>
        <img src=${posterUrl} alt=${video.title}>
        <div class="video-card__overlay">
          <div class="meta-row">
            <span class="pill">${video.category}</span>
            <span class="pill pill--muted">${video.access_label}</span>
          </div>
          <span class="video-card__duration">${video.duration_label}</span>
        </div>
      </a>
      <div class="video-card__body">
        <h3>${video.title}</h3>
        <p>${video.synopsis}</p>
        <div class="video-card__footer">
          <span>${video.creator_name}</span>
          <a class="text-link" href=${href}>View details</a>
        </div>
      </div>
    </article>
  `;
}

function renderEmptyState() {
  return html`
    <article class="empty-state">
      <span class="eyebrow">NO RESULTS</span>
      <h3>No videos match these filters.</h3>
      <p>Change the search, category, or access filter and try again.</p>
    </article>
  `;
}

function CatalogApp() {
  const videos = signal(initialVideos);
  const query = signal("");
  const category = signal("all");
  const access = signal("all");
  const sort = signal("recent");

  const categories = computed(() => {
    const list = videos.value.map((video) => video.category);
    return ["all", ...new Set(list)];
  });

  const filteredVideos = computed(() => {
    const sanitizedQuery = query.value.trim().toLowerCase();

    const matches = videos.value.filter((video) => {
      const byQuery =
        sanitizedQuery === "" ||
        video.title.toLowerCase().includes(sanitizedQuery) ||
        video.creator_name.toLowerCase().includes(sanitizedQuery);

      const byCategory = category.value === "all" || video.category === category.value;
      const byAccess = access.value === "all" || video.access_level === access.value;

      return byQuery && byCategory && byAccess;
    });

    return sortVideos(matches, sort.value);
  });

  const paidCount = computed(() => videos.value.filter((video) => video.access_level !== "free").length);
  const resultLabel = computed(() => {
    const count = filteredVideos.value.length;
    return `${numberFormatter.format(count)} ${count === 1 ? "video found" : "videos found"}`;
  });

  return html`
    <section class="catalog-ui">
      <div class="catalog-ui__toolbar">
        <label class="field">
          <span>Search by title or creator</span>
          <input
            type="search"
            placeholder="Search..."
            value=${() => query.value}
            on:input=${(event) => {
              query.value = event.target.value;
            }}
          >
        </label>
        <div class="catalog-ui__stats">
          <article class="mini-stat">
            <span>Results</span>
            <strong>${() => numberFormatter.format(filteredVideos.value.length)}</strong>
          </article>
          <article class="mini-stat">
            <span>Paid content</span>
            <strong>${() => numberFormatter.format(paidCount.value)}</strong>
          </article>
          <article class="mini-stat">
            <span>Total library</span>
            <strong>${() => numberFormatter.format(videos.value.length)}</strong>
          </article>
        </div>
      </div>

      <div class="catalog-ui__panel">
        <div class="catalog-ui__group">
          <span class="field-label">Category</span>
          <div class="chip-row">${() => categories.value.map((item) => categoryChip(item, category))}</div>
        </div>
        <div class="catalog-ui__group">
          <span class="field-label">Access</span>
          <div class="toggle-row">
            ${accessToggle("all", "All", access)}
            ${accessToggle("free", "Free", access)}
            ${accessToggle("premium", "Premium", access)}
          </div>
        </div>
        <div class="catalog-ui__group">
          <span class="field-label">Sort</span>
          <div class="toggle-row">
            ${sortToggle("recent", "Newest", sort)}
            ${sortToggle("duration", "Longest", sort)}
            ${sortToggle("title", "A-Z", sort)}
          </div>
        </div>
      </div>

      <div class="catalog-ui__summary">
        <p>${() => resultLabel.value}</p>
        <span>${bootstrap.usingFallback ? "Demo mode is active." : "Live data from the PHP backend."}</span>
      </div>

      <div class="catalog-grid">
        ${() => filteredVideos.value.length > 0 ? filteredVideos.value.map((video) => renderVideoCard(video)) : renderEmptyState()}
      </div>
    </section>
  `;
}

function AgeGateApp() {
  const visible = signal(!bootstrap.ageVerified);
  const status = signal("");

  effect(() => {
    document.body.classList.toggle("is-locked", visible.value);
  });

  async function confirmAge() {
    status.value = "Saving confirmation...";

    try {
      const response = await fetch(`${baseUrl}/api/session.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ action: "verify-age" }),
      });

      const data = await response.json();

      if (!response.ok || !data.ok) {
        throw new Error(data.message ?? "Could not save your confirmation.");
      }

      visible.value = false;
      status.value = "";
    } catch (error) {
      status.value = error instanceof Error ? error.message : "Unexpected error.";
    }
  }

  return html`
    <div class=${() => visible.value ? "age-gate age-gate--visible" : "age-gate"}>
      <div class="age-gate__panel">
        <span class="eyebrow">18+ ONLY</span>
        <h2>Before you enter</h2>
        <p>This site contains adult content and is only for people who are 18 or older.</p>
        <ul class="age-gate__list">
          <li>By entering, you confirm legal adult age.</li>
          <li>This platform should keep creator identity and consent records.</li>
          <li>Adult content must stay clearly marked as adult content.</li>
        </ul>
        <div class="hero__actions">
          <button class="button" type="button" on:click=${confirmAge}>I am 18+</button>
          <a class="button button--ghost" href=${exitUrl} rel="noreferrer">Leave</a>
        </div>
        <p class="form-note">${() => status.value}</p>
      </div>
    </div>
  `;
}

function CookieNoticeApp() {
  const settings = bootstrap.cookieNotice ?? {};
  const storageKey = settings.storageKey ?? "videw_cookie_notice";
  let accepted = false;

  try {
    accepted = window.localStorage.getItem(storageKey) === "accepted";
  } catch (error) {
    accepted = false;
  }

  const visible = signal(Boolean(settings.enabled) && Boolean(settings.text) && !accepted);

  function acceptNotice() {
    try {
      window.localStorage.setItem(storageKey, "accepted");
    } catch (error) {
      // Ignore storage errors and just hide the banner for the current page view.
    }

    visible.value = false;
  }

  return html`
    <div class=${() => visible.value ? "cookie-banner cookie-banner--visible" : "cookie-banner"}>
      <div class="cookie-banner__panel">
        <div class="cookie-banner__body">
          <strong>${settings.title ?? "Cookie notice"}</strong>
          <p>${settings.text ?? ""}</p>
        </div>
        <div class="cookie-banner__actions">
          ${() => settings.linkUrl && settings.linkLabel ? html`<a class="button button--ghost" href=${settings.linkUrl}>${settings.linkLabel}</a>` : ""}
          <button class="button" type="button" on:click=${acceptNotice}>${settings.acceptLabel ?? "Accept"}</button>
        </div>
      </div>
    </div>
  `;
}

function mountCatalog() {
  const target = document.querySelector("#catalog-app");

  if (target && bootstrap.page === "home") {
    createApp(target, CatalogApp);
  }
}

function mountAgeGate() {
  const target = document.querySelector("#age-gate-root");

  if (target) {
    createApp(target, AgeGateApp);
  }
}

function mountCookieNotice() {
  const target = document.querySelector("#cookie-notice-root");

  if (target) {
    createApp(target, CookieNoticeApp);
  }
}

mountCatalog();
mountCookieNotice();
mountAgeGate();
