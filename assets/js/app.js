import { createApp, computed, effect, html, signal } from "@bragamateus/gui";

const bootstrap = window.__VIDEW__ ?? {};
const baseUrl = bootstrap.baseUrl ?? "";
const exitUrl = bootstrap.exitUrl ?? "https://www.google.com";
const sessionApiCsrf = bootstrap.security?.sessionApiCsrf ?? "";
const initialVideos = Array.isArray(bootstrap.videos) ? bootstrap.videos : [];
const initialFilters = bootstrap.initialFilters ?? {};
const catalogCopy = bootstrap.catalogCopy ?? {};
const numberFormatter = new Intl.NumberFormat("en-US");

function watchUrl(slug) {
  return `${baseUrl}/watch.php?slug=${encodeURIComponent(slug)}`;
}

function initials(value) {
  const input = `${value ?? ""}`.trim();

  if (input === "") {
    return "V";
  }

  return input.slice(0, 1).toUpperCase();
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
  const posterPosition = video.poster_object_position ?? `${video.poster_focus_x ?? 50}% ${video.poster_focus_y ?? 50}%`;

  return html`
    <article class="front-feed-card">
      <a class="front-feed-card__thumb" href=${href}>
        <img src=${posterUrl} alt=${video.title} style=${`object-position: ${posterPosition};`}>
        <span class="front-duration">${video.duration_label}</span>
      </a>
      <div class="front-feed-card__body">
        <div class="front-avatar">${initials(video.creator_name)}</div>
        <div class="front-feed-card__meta">
          <h3>${video.title}</h3>
          <p>${video.creator_name}</p>
          <span>${video.access_label} • ${video.published_label ?? video.duration_label}</span>
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
  const query = signal(typeof initialFilters.query === "string" ? initialFilters.query : "");
  const category = signal(typeof initialFilters.category === "string" ? initialFilters.category : "all");
  const access = signal(typeof initialFilters.access === "string" ? initialFilters.access : "all");
  const sort = signal(typeof initialFilters.sort === "string" ? initialFilters.sort : "recent");

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
          "X-CSRF-Token": sessionApiCsrf,
        },
        body: JSON.stringify({ action: "verify-age", _csrf: sessionApiCsrf }),
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

function mountWatchMedia() {
  const players = document.querySelectorAll(".watch-player");

  players.forEach((player) => {
    if (!(player instanceof HTMLVideoElement)) {
      return;
    }

    const shell = player.closest(".watch-player-shell");

    if (!(shell instanceof HTMLElement)) {
      return;
    }

    const applyAspect = () => {
      const width = player.videoWidth;
      const height = player.videoHeight;

      if (width <= 0 || height <= 0) {
        return;
      }

      const ratio = width / height;
      shell.style.setProperty("--watch-media-ratio", `${ratio}`);
      shell.classList.toggle("is-portrait", ratio < 1);
      shell.classList.toggle("is-landscape", ratio >= 1);
    };

    if (player.readyState >= 1) {
      applyAspect();
    } else {
      player.addEventListener("loadedmetadata", applyAspect, { once: true });
    }
  });
}

function mountPublicShell() {
  const header = document.querySelector(".site-header.shell-header");
  const syncShellMetrics = () => {
    if (!(document.body instanceof HTMLBodyElement) || !document.body.classList.contains("public-layout")) {
      return;
    }

    if (!(header instanceof HTMLElement)) {
      return;
    }

    const height = Math.ceil(header.getBoundingClientRect().height);

    if (height > 0) {
      document.body.style.setProperty("--shell-header-height", `${height}px`);
    }
  };

  syncShellMetrics();

  if (header instanceof HTMLElement) {
    if ("ResizeObserver" in window) {
      const observer = new ResizeObserver(() => {
        syncShellMetrics();
      });

      observer.observe(header);
    }

    window.addEventListener("resize", syncShellMetrics, { passive: true });
    window.addEventListener("load", syncShellMetrics, { once: true });
  }

  const toggle = document.querySelector("[data-shell-menu]");
  const sidebar = document.querySelector("[data-shell-sidebar]");
  const overlay = document.querySelector("[data-shell-overlay]");

  if (!(toggle instanceof HTMLButtonElement) || !(sidebar instanceof HTMLElement) || !(overlay instanceof HTMLElement)) {
    return;
  }

  const closeMenu = () => {
    sidebar.classList.remove("is-visible");
    overlay.classList.remove("is-visible");
    document.body.classList.remove("shell-nav-open");
    toggle.setAttribute("aria-expanded", "false");
  };

  const openMenu = () => {
    sidebar.classList.add("is-visible");
    overlay.classList.add("is-visible");
    document.body.classList.add("shell-nav-open");
    toggle.setAttribute("aria-expanded", "true");
  };

  toggle.setAttribute("aria-expanded", "false");

  toggle.addEventListener("click", () => {
    if (sidebar.classList.contains("is-visible")) {
      closeMenu();
      return;
    }

    openMenu();
  });

  overlay.addEventListener("click", closeMenu);

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });

  sidebar.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 1180) {
        closeMenu();
      }
    });
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 1180) {
      closeMenu();
    }

    syncShellMetrics();
  });
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
  if (!["admin", "studio"].includes(bootstrap.page)) {
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

  const mountPosterFraming = (form) => {
    const framing = form.querySelector("[data-poster-framing]");

    if (!(framing instanceof HTMLElement)) {
      return;
    }

    const previewImages = framing.querySelectorAll("[data-poster-preview-image]");
    const focusX = form.querySelector('[data-poster-focus="x"]');
    const focusY = form.querySelector('[data-poster-focus="y"]');
    const focusXValueNode = framing.querySelector('[data-poster-focus-value="x"]');
    const focusYValueNode = framing.querySelector('[data-poster-focus-value="y"]');
    const posterMode = form.querySelector('[data-media-switch="poster"]');
    const posterUrlInput = form.querySelector('input[name="poster_external_url"]');
    const posterFileInput = form.querySelector('input[name="poster_file"]');
    const fallbackPoster = framing.dataset.currentPoster ?? "";
    let objectUrl = "";
    let activeFile = null;

    const releaseObjectUrl = () => {
      if (objectUrl !== "") {
        URL.revokeObjectURL(objectUrl);
        objectUrl = "";
      }

      activeFile = null;
    };

    const resolvePreviewSource = () => {
      const mode = posterMode instanceof HTMLSelectElement ? posterMode.value : "";

      if (mode === "url" && posterUrlInput instanceof HTMLInputElement) {
        return posterUrlInput.value.trim();
      }

      if (mode === "upload" && posterFileInput instanceof HTMLInputElement && posterFileInput.files?.[0]) {
        const nextFile = posterFileInput.files[0];

        if (objectUrl === "" || activeFile !== nextFile) {
          releaseObjectUrl();
          objectUrl = URL.createObjectURL(nextFile);
          activeFile = nextFile;
        }

        return objectUrl;
      }

      releaseObjectUrl();
      return fallbackPoster;
    };

    const syncPreview = () => {
      const focusXValue = focusX instanceof HTMLInputElement ? focusX.value : "50";
      const focusYValue = focusY instanceof HTMLInputElement ? focusY.value : "50";
      const src = resolvePreviewSource();
      const visible = src !== "";

      if (focusXValueNode instanceof HTMLElement) {
        focusXValueNode.textContent = `${focusXValue}%`;
      }

      if (focusYValueNode instanceof HTMLElement) {
        focusYValueNode.textContent = `${focusYValue}%`;
      }

      framing.classList.toggle("is-empty", !visible);

      previewImages.forEach((image) => {
        if (!(image instanceof HTMLImageElement)) {
          return;
        }

        image.style.objectPosition = `${focusXValue}% ${focusYValue}%`;

        if (visible) {
          image.src = src;
        } else {
          image.removeAttribute("src");
        }
      });
    };

    syncPreview();

    [focusX, focusY, posterMode, posterUrlInput, posterFileInput].forEach((field) => {
      if (!(field instanceof HTMLElement)) {
        return;
      }

      const eventName = field instanceof HTMLInputElement && field.type === "file" ? "change" : "input";
      field.addEventListener(eventName, syncPreview);

      if (eventName !== "change") {
        field.addEventListener("change", syncPreview);
      }
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

    mountPosterFraming(form);
  });
}

function mountCopyEditor() {
  const root = document.querySelector("[data-copy-editor]");

  if (!(root instanceof HTMLElement)) {
    return;
  }

  const selector = root.querySelector("[data-copy-section-selector]");
  const summary = root.querySelector("[data-copy-section-summary]");
  const panels = document.querySelectorAll("[data-copy-section-panel]");

  if (!(selector instanceof HTMLSelectElement) || !(summary instanceof HTMLElement) || panels.length === 0) {
    return;
  }

  const updateActiveSection = () => {
    const activeId = selector.value;

    panels.forEach((panel) => {
      if (!(panel instanceof HTMLElement)) {
        return;
      }

      const matches = panel.dataset.copySectionPanel === activeId;
      panel.hidden = !matches;
    });

    const activeOption = selector.selectedOptions[0];
    const activePanel = Array.from(panels).find((panel) => panel instanceof HTMLElement && panel.dataset.copySectionPanel === activeId);
    const title = activeOption?.textContent?.trim() || "Copy";
    const description = activePanel instanceof HTMLElement ? activePanel.querySelector(".admin-form-section__header p")?.textContent?.trim() ?? "" : "";
    const titleNode = summary.querySelector("strong");
    const descriptionNode = summary.querySelector("p");

    if (titleNode instanceof HTMLElement) {
      titleNode.textContent = title;
    }

    if (descriptionNode instanceof HTMLElement) {
      descriptionNode.textContent = description;
    }
  };

  selector.addEventListener("change", updateActiveSection);
  updateActiveSection();
}

mountCatalog();
mountWatchMedia();
mountPublicShell();
mountCookieNotice();
mountAgeGate();
mountAdminMediaForms();
mountCopyEditor();
