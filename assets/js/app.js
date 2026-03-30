import { createApp, computed, effect, html, signal } from "@bragamateus/gui";

const bootstrap = window.__VIDEW__ ?? {};
const baseUrl = bootstrap.baseUrl ?? "";
const exitUrl = bootstrap.exitUrl ?? "https://www.google.com";
const initialVideos = Array.isArray(bootstrap.videos) ? bootstrap.videos : [];
const catalogCopy = bootstrap.catalogCopy ?? {};
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
  const chipLabel = label === "all" ? (catalogCopy.accessAll ?? "All") : label;

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
          <a class="text-link" href=${href}>${catalogCopy.watchNow ?? "Watch now"}</a>
        </div>
      </div>
    </article>
  `;
}

function renderEmptyState() {
  return html`
    <article class="empty-state">
      <span class="eyebrow">${catalogCopy.emptyEyebrow ?? "NO RESULTS"}</span>
      <h3>${catalogCopy.emptyTitle ?? "No videos match your filters."}</h3>
      <p>${catalogCopy.emptyText ?? "Try another word or change one of the filters."}</p>
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
    return `${numberFormatter.format(count)} ${count === 1 ? (catalogCopy.resultsOne ?? "video found") : (catalogCopy.resultsMany ?? "videos found")}`;
  });

  return html`
    <section class="catalog-ui">
      <div class="catalog-ui__toolbar">
        <label class="field">
          <span>${catalogCopy.searchLabel ?? "Search by title or creator"}</span>
          <input
            type="search"
            placeholder=${catalogCopy.searchPlaceholder ?? "Search titles or creators"}
            value=${() => query.value}
            on:input=${(event) => {
              query.value = event.target.value;
            }}
          >
        </label>
        <div class="catalog-ui__stats">
          <article class="mini-stat">
            <span>${catalogCopy.resultsLabel ?? "Results"}</span>
            <strong>${() => numberFormatter.format(filteredVideos.value.length)}</strong>
          </article>
          <article class="mini-stat">
            <span>${catalogCopy.premiumLabel ?? "Premium videos"}</span>
            <strong>${() => numberFormatter.format(paidCount.value)}</strong>
          </article>
          <article class="mini-stat">
            <span>${catalogCopy.totalLabel ?? "Total library"}</span>
            <strong>${() => numberFormatter.format(videos.value.length)}</strong>
          </article>
        </div>
      </div>

      <div class="catalog-ui__panel">
        <div class="catalog-ui__group">
          <span class="field-label">${catalogCopy.categoryLabel ?? "Category"}</span>
          <div class="chip-row">${() => categories.value.map((item) => categoryChip(item, category))}</div>
        </div>
        <div class="catalog-ui__group">
          <span class="field-label">${catalogCopy.accessLabel ?? "Access"}</span>
          <div class="toggle-row">
            ${accessToggle("all", catalogCopy.accessAll ?? "All", access)}
            ${accessToggle("free", catalogCopy.accessFree ?? "Free", access)}
            ${accessToggle("premium", catalogCopy.accessPremium ?? "Premium", access)}
          </div>
        </div>
        <div class="catalog-ui__group">
          <span class="field-label">${catalogCopy.sortLabel ?? "Sort"}</span>
          <div class="toggle-row">
            ${sortToggle("recent", catalogCopy.sortRecent ?? "Newest", sort)}
            ${sortToggle("duration", catalogCopy.sortDuration ?? "Longest", sort)}
            ${sortToggle("title", catalogCopy.sortTitle ?? "A-Z", sort)}
          </div>
        </div>
      </div>

      <div class="catalog-ui__summary">
        <p>${() => resultLabel.value}</p>
        <span>${bootstrap.usingFallback ? (catalogCopy.summaryFallback ?? "Preview catalog is active.") : (catalogCopy.summaryLive ?? "Browse the latest videos below.")}</span>
      </div>

      <div class="catalog-grid">
        ${() => filteredVideos.value.length > 0 ? filteredVideos.value.map((video) => renderVideoCard(video)) : renderEmptyState()}
      </div>
    </section>
  `;
}

function AgeGateApp() {
  const settings = bootstrap.ageGate ?? {};
  const visible = signal(Boolean(settings.enabled) && !bootstrap.ageVerified);
  const status = signal("");

  effect(() => {
    document.body.classList.toggle("is-locked", visible.value);
  });

  async function confirmAge() {
    status.value = settings.savingLabel ?? "Saving your confirmation...";

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
        throw new Error(data.message ?? settings.saveErrorLabel ?? "We could not save your confirmation.");
      }

      visible.value = false;
      status.value = "";
    } catch (error) {
      status.value = error instanceof Error ? error.message : settings.errorLabel ?? "Something went wrong. Please try again.";
    }
  }

  return html`
    <div class=${() => visible.value ? "age-gate age-gate--visible" : "age-gate"}>
      <div class="age-gate__panel">
        <span class="eyebrow">${settings.eyebrow ?? "18+ ONLY"}</span>
        <h2>${settings.title ?? "Before you enter"}</h2>
        <p>${settings.text ?? "This site contains age-restricted content and is only for people who are 18 or older."}</p>
        <ul class="age-gate__list">
          <li>${settings.item1 ?? "By entering, you confirm that you are 18 or older."}</li>
          <li>${settings.item2 ?? "Restricted content stays clearly marked across the site."}</li>
          <li>${settings.item3 ?? "You can leave this page at any time."}</li>
        </ul>
        <div class="hero__actions">
          <button class="button" type="button" on:click=${confirmAge}>${settings.confirmLabel ?? "I am 18+"}</button>
          <a class="button button--ghost" href=${exitUrl} rel="noreferrer">${settings.leaveLabel ?? "Leave"}</a>
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

  if (target && bootstrap.page === "browse") {
    createApp(target, CatalogApp);
  }
}

function mountAgeGate() {
  const target = document.querySelector("#age-gate-root");

  if (target && bootstrap.ageGate?.enabled) {
    createApp(target, AgeGateApp);
  }
}

function mountCookieNotice() {
  const target = document.querySelector("#cookie-notice-root");

  if (target) {
    createApp(target, CookieNoticeApp);
  }
}

function mountAdminMediaForms() {
  if (bootstrap.page !== "admin") {
    return;
  }

  const forms = document.querySelectorAll("[data-media-source-form]");

  const syncGroups = (form, key) => {
    const select = form.querySelector(`[data-media-switch="${key}"]`);

    if (!(select instanceof HTMLSelectElement)) {
      return;
    }

    const mode = select.value;
    const groups = form.querySelectorAll(`[data-media-group="${key}"]`);

    groups.forEach((group) => {
      const visible = group.dataset.mediaMode === mode;
      group.style.display = visible ? "" : "none";

      group.querySelectorAll("input, select, textarea").forEach((field) => {
        field.disabled = !visible;
      });
    });
  };

  forms.forEach((form) => {
    ["video", "poster"].forEach((key) => {
      const select = form.querySelector(`[data-media-switch="${key}"]`);

      if (!(select instanceof HTMLSelectElement)) {
        return;
      }

      syncGroups(form, key);
      select.addEventListener("change", () => syncGroups(form, key));
    });
  });
}

mountCatalog();
mountCookieNotice();
mountAgeGate();
mountAdminMediaForms();
