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
  const shells = document.querySelectorAll(".watch-player-shell");

  const applyAspectToShell = (shell, ratio) => {
    if (!(shell instanceof HTMLElement) || !Number.isFinite(ratio) || ratio <= 0) {
      return;
    }

    shell.style.setProperty("--watch-media-ratio", `${ratio}`);
    shell.classList.toggle("is-portrait", ratio < 1);
    shell.classList.toggle("is-landscape", ratio >= 1);
  };

  const inferAspectFromPoster = (shell) => {
    if (!(shell instanceof HTMLElement)) {
      return;
    }

    const poster = shell.dataset.watchPoster ?? "";

    if (poster === "") {
      return;
    }

    const image = new Image();
    image.addEventListener("load", () => {
      if (image.naturalWidth > 0 && image.naturalHeight > 0) {
        applyAspectToShell(shell, image.naturalWidth / image.naturalHeight);
      }
    }, { once: true });
    image.src = poster;
  };

  const parsePrerollPayload = (shell) => {
    if (!(shell instanceof HTMLElement)) {
      return null;
    }

    const raw = shell.dataset.watchPreroll ?? "";

    if (raw === "") {
      return null;
    }

    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === "object" ? parsed : null;
    } catch (_error) {
      return null;
    }
  };

  const fireTrackingPixels = (urls) => {
    if (!Array.isArray(urls)) {
      return;
    }

    urls.forEach((url) => {
      if (typeof url !== "string" || url.trim() === "") {
        return;
      }

      const beacon = new Image();
      const separator = url.includes("?") ? "&" : "?";
      beacon.src = `${url}${separator}cb=${Date.now()}`;
    });
  };

  const mountPrerollForShell = (shell) => {
    if (!(shell instanceof HTMLElement)) {
      return;
    }

    const payload = parsePrerollPayload(shell);

    if (!payload) {
      return;
    }

    const layer = shell.querySelector("[data-watch-preroll-layer]");
    const adVideo = shell.querySelector("[data-watch-preroll-video]");
    const adImage = shell.querySelector("[data-watch-preroll-image]");
    const skipButton = shell.querySelector("[data-watch-preroll-skip]");
    const cta = shell.querySelector("[data-watch-preroll-cta]");
    const progressBar = shell.querySelector("[data-watch-preroll-progress]");
    const mainVideo = shell.querySelector("[data-watch-player-video]");
    const playerRoot = shell.querySelector("[data-watch-player]");
    const embed = shell.querySelector(".watch-embed");
    const embedSource = embed instanceof HTMLIFrameElement ? (embed.dataset.watchEmbedSrc ?? "") : "";
    const skipAfterSeconds = Math.max(0, Number(payload.skip_after_seconds ?? 5) || 0);
    const tracking = payload.tracking && typeof payload.tracking === "object" ? payload.tracking : {};
    const firedEvents = new Set();
    const adType = String(payload.ad_type ?? "video");
    const isImageAd = adType === "image";
      let released = false;
      let imageElapsedSeconds = 0;
      let imageIntervalId = 0;

    if (!(layer instanceof HTMLElement)) {
      return;
    }

      const fireEvent = (eventName) => {
      if (firedEvents.has(eventName)) {
        return;
      }

      firedEvents.add(eventName);
        fireTrackingPixels(tracking[eventName]);
      };

      const restoreShellAspect = () => {
        if (mainVideo instanceof HTMLVideoElement && mainVideo.videoWidth > 0 && mainVideo.videoHeight > 0) {
          applyAspectToShell(shell, mainVideo.videoWidth / mainVideo.videoHeight);
          return;
        }

        inferAspectFromPoster(shell);
      };

      const releaseContent = ({ skipped = false } = {}) => {
        if (released) {
          return;
        }

      released = true;
      shell.classList.remove("is-preroll-active");
      layer.hidden = true;
      if (adVideo instanceof HTMLVideoElement) {
        adVideo.pause();
      }

        if (imageIntervalId) {
          window.clearInterval(imageIntervalId);
          imageIntervalId = 0;
        }

        restoreShellAspect();

        if (skipped) {
          fireEvent("skip");
        } else {
        fireEvent("complete");
      }

      if (embed instanceof HTMLIFrameElement && embedSource !== "" && embed.getAttribute("src") !== embedSource) {
        embed.setAttribute("src", embedSource);
      }

      if (playerRoot instanceof HTMLElement) {
        playerRoot.classList.remove("is-preroll-pending");
      }

      if (mainVideo instanceof HTMLVideoElement) {
        void mainVideo.play().catch(() => {});
      }

      if (progressBar instanceof HTMLElement) {
        progressBar.style.width = "100%";
      }
    };

    const updateSkipButton = () => {
      if (!(skipButton instanceof HTMLButtonElement)) {
        return;
      }

      const currentProgress = isImageAd
        ? imageElapsedSeconds
        : ((adVideo instanceof HTMLVideoElement && Number.isFinite(adVideo.currentTime)) ? adVideo.currentTime : 0);
      const remaining = Math.max(0, Math.ceil(skipAfterSeconds - currentProgress));
      const canSkip = skipAfterSeconds === 0 || currentProgress >= skipAfterSeconds;

      skipButton.disabled = !canSkip;
      skipButton.textContent = isImageAd
        ? (canSkip ? "Continue" : `Continue in ${remaining}s`)
        : (canSkip ? "Skip ad" : `Skip in ${remaining}s`);
    };

    const updateProgressBar = () => {
      if (!(progressBar instanceof HTMLElement)) {
        return;
      }

      let progress = 0;

      if (isImageAd) {
        progress = skipAfterSeconds > 0 ? Math.min(1, imageElapsedSeconds / skipAfterSeconds) : 1;
      } else if (adVideo instanceof HTMLVideoElement && Number.isFinite(adVideo.duration) && adVideo.duration > 0) {
        progress = Math.min(1, adVideo.currentTime / adVideo.duration);
      }

      progressBar.style.width = `${Math.max(0, Math.min(100, progress * 100))}%`;
    };

    shell.classList.add("is-preroll-active");
    layer.hidden = false;

    if (cta instanceof HTMLAnchorElement) {
      cta.addEventListener("click", () => {
        fireTrackingPixels(tracking.clickTracking);
      });
    }

    if (skipButton instanceof HTMLButtonElement) {
      skipButton.addEventListener("click", () => {
        if (skipButton.disabled) {
          return;
        }

        releaseContent({ skipped: true });
      });
    }

    if (playerRoot instanceof HTMLElement) {
      playerRoot.classList.add("is-preroll-pending");
    }

    if (isImageAd) {
      if (adVideo instanceof HTMLVideoElement) {
        adVideo.hidden = true;
        adVideo.removeAttribute("src");
      }

        if (adImage instanceof HTMLImageElement) {
          adImage.hidden = false;
          adImage.src = String(payload.media_url ?? "");
          if (adImage.complete && adImage.naturalWidth > 0 && adImage.naturalHeight > 0) {
            applyAspectToShell(shell, adImage.naturalWidth / adImage.naturalHeight);
          } else {
            adImage.addEventListener("load", () => {
              if (adImage.naturalWidth > 0 && adImage.naturalHeight > 0) {
                applyAspectToShell(shell, adImage.naturalWidth / adImage.naturalHeight);
              }
            }, { once: true });
          }
        }

        fireEvent("impression");
      fireEvent("start");
      updateSkipButton();
      updateProgressBar();

      if (skipAfterSeconds === 0) {
        releaseContent();
        return;
      }

      imageIntervalId = window.setInterval(() => {
        imageElapsedSeconds += 1;
        updateSkipButton();
        updateProgressBar();

        if (imageElapsedSeconds >= skipAfterSeconds) {
          releaseContent();
        }
      }, 1000);

      return;
    }

    if (!(adVideo instanceof HTMLVideoElement)) {
      return;
    }

    if (adImage instanceof HTMLImageElement) {
      adImage.hidden = true;
      adImage.removeAttribute("src");
    }

      adVideo.hidden = false;
      adVideo.src = String(payload.media_url ?? "");
      adVideo.muted = true;
      adVideo.playsInline = true;
      adVideo.preload = "auto";

      adVideo.addEventListener("loadedmetadata", () => {
        if (adVideo.videoWidth > 0 && adVideo.videoHeight > 0) {
          applyAspectToShell(shell, adVideo.videoWidth / adVideo.videoHeight);
        }
      }, { once: true });

      adVideo.addEventListener("play", () => {
        fireEvent("impression");
      fireEvent("start");
    }, { once: true });

    adVideo.addEventListener("timeupdate", () => {
      updateSkipButton();
      updateProgressBar();

      if (!Number.isFinite(adVideo.duration) || adVideo.duration <= 0) {
        return;
      }

      const progress = adVideo.currentTime / adVideo.duration;

      if (progress >= 0.25) {
        fireEvent("firstQuartile");
      }

      if (progress >= 0.5) {
        fireEvent("midpoint");
      }

      if (progress >= 0.75) {
        fireEvent("thirdQuartile");
      }
    });

    adVideo.addEventListener("ended", () => {
      releaseContent();
    });

    adVideo.addEventListener("error", () => {
      releaseContent();
    });

    updateSkipButton();
    updateProgressBar();
    void adVideo.play().catch(() => {
      updateSkipButton();
      updateProgressBar();
    });
  };

  shells.forEach((shell) => {
    const player = shell.querySelector("[data-watch-player-video]");

    if (!(player instanceof HTMLVideoElement)) {
      inferAspectFromPoster(shell);
      mountPrerollForShell(shell);
      return;
    }

    mountPrerollForShell(shell);
  });

  const players = document.querySelectorAll("[data-watch-player]");

  const formatTime = (seconds) => {
    if (!Number.isFinite(seconds) || seconds < 0) {
      return "0:00";
    }

    const totalSeconds = Math.floor(seconds);
    const minutes = Math.floor(totalSeconds / 60);
    const remainingSeconds = totalSeconds % 60;
    const hours = Math.floor(minutes / 60);
    const displayMinutes = hours > 0 ? minutes % 60 : minutes;

    if (hours > 0) {
      return `${hours}:${String(displayMinutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
    }

    return `${displayMinutes}:${String(remainingSeconds).padStart(2, "0")}`;
  };

  players.forEach((root) => {
    if (!(root instanceof HTMLElement)) {
      return;
    }

    const player = root.querySelector("[data-watch-player-video]");

    if (!(player instanceof HTMLVideoElement)) {
      return;
    }

    const shell = player.closest(".watch-player-shell");
    const playButton = root.querySelector("[data-watch-toggle-play]");
    const playLabel = root.querySelector("[data-watch-play-label]");
    const muteButton = root.querySelector("[data-watch-toggle-mute]");
    const fullscreenButton = root.querySelector("[data-watch-toggle-fullscreen]");
    const seekInput = root.querySelector("[data-watch-seek]");
    const volumeInput = root.querySelector("[data-watch-volume]");
    const currentTimeNode = root.querySelector("[data-watch-current-time]");
    const durationNode = root.querySelector("[data-watch-duration]");
    const HIDE_DELAY_MS = 2400;
    const SEEK_STEP_SECONDS = 10;
    let hideTimer = 0;

    if (!(shell instanceof HTMLElement)) {
      return;
    }

    const clearHideTimer = () => {
      if (hideTimer !== 0) {
        window.clearTimeout(hideTimer);
        hideTimer = 0;
      }
    };

    const showControls = () => {
      root.classList.remove("is-controls-hidden");
    };

    const hideControls = () => {
      if (player.paused) {
        return;
      }

      root.classList.add("is-controls-hidden");
    };

    const scheduleControlsHide = () => {
      clearHideTimer();

      if (player.paused) {
        showControls();
        return;
      }

      hideTimer = window.setTimeout(() => {
        hideControls();
      }, HIDE_DELAY_MS);
    };

    const handleActivity = () => {
      showControls();
      scheduleControlsHide();
    };

    const isInteractiveTarget = (target) =>
      target instanceof HTMLElement &&
      (target.closest("button, input, select, textarea, a") instanceof HTMLElement);

    const applyAspect = () => {
      const width = player.videoWidth;
      const height = player.videoHeight;

      if (width <= 0 || height <= 0) {
        return;
      }

      applyAspectToShell(shell, width / height);
    };

    const syncPlayState = () => {
      const isPaused = player.paused;
      root.classList.toggle("is-playing", !isPaused);

      if (playLabel instanceof HTMLElement) {
        playLabel.textContent = isPaused ? "Play" : "Pause";
      }

      if (isPaused) {
        clearHideTimer();
        showControls();
      } else {
        scheduleControlsHide();
      }
    };

    const syncTimeState = () => {
      const duration = Number.isFinite(player.duration) ? player.duration : 0;
      const currentTime = Number.isFinite(player.currentTime) ? player.currentTime : 0;
      const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

      if (currentTimeNode instanceof HTMLElement) {
        currentTimeNode.textContent = formatTime(currentTime);
      }

      if (durationNode instanceof HTMLElement) {
        durationNode.textContent = formatTime(duration);
      }

      if (seekInput instanceof HTMLInputElement) {
        seekInput.value = String(progress);
      }
    };

    const syncVolumeState = () => {
      const muted = player.muted || player.volume === 0;
      root.classList.toggle("is-muted", muted);

      if (muteButton instanceof HTMLElement) {
        muteButton.textContent = muted ? "Unmute" : "Mute";
      }

      if (volumeInput instanceof HTMLInputElement) {
        volumeInput.value = String(player.muted ? 0 : player.volume);
      }
    };

    const togglePlayback = async () => {
      if (player.paused) {
        try {
          await player.play();
        } catch (_error) {
          return;
        }
      } else {
        player.pause();
      }
    };

    const toggleFullscreen = async () => {
      if (!(document.fullscreenElement instanceof Element)) {
        try {
          await shell.requestFullscreen();
        } catch (_error) {
          return;
        }

        return;
      }

      if (document.fullscreenElement === shell) {
        try {
          await document.exitFullscreen();
        } catch (_error) {
          return;
        }
      }
    };

    const seekBy = (deltaSeconds) => {
      if (!Number.isFinite(player.duration) || player.duration <= 0) {
        return;
      }

      player.currentTime = Math.min(player.duration, Math.max(0, player.currentTime + deltaSeconds));
      syncTimeState();
    };

    const handleKeyboardShortcut = (event) => {
      if (!(event instanceof KeyboardEvent)) {
        return;
      }

      if (isInteractiveTarget(event.target) && event.target !== root) {
        return;
      }

      const key = event.key;

      if (key === " " || key === "Spacebar") {
        event.preventDefault();
        handleActivity();
        void togglePlayback();
        return;
      }

      if (key === "m" || key === "M") {
        event.preventDefault();
        player.muted = !player.muted;
        syncVolumeState();
        handleActivity();
        return;
      }

      if (key === "f" || key === "F") {
        event.preventDefault();
        handleActivity();
        void toggleFullscreen();
        return;
      }

      if (key === "ArrowLeft") {
        event.preventDefault();
        seekBy(-SEEK_STEP_SECONDS);
        handleActivity();
        return;
      }

      if (key === "ArrowRight") {
        event.preventDefault();
        seekBy(SEEK_STEP_SECONDS);
        handleActivity();
      }
    };

    root.tabIndex = 0;

    if (playButton instanceof HTMLButtonElement) {
      playButton.addEventListener("click", () => {
        handleActivity();
        void togglePlayback();
      });
    }

    if (muteButton instanceof HTMLButtonElement) {
      muteButton.addEventListener("click", () => {
        player.muted = !player.muted;
        syncVolumeState();
        handleActivity();
      });
    }

    if (fullscreenButton instanceof HTMLButtonElement) {
      fullscreenButton.addEventListener("click", () => {
        handleActivity();
        void toggleFullscreen();
      });
    }

    if (seekInput instanceof HTMLInputElement) {
      seekInput.addEventListener("input", () => {
        const duration = Number.isFinite(player.duration) ? player.duration : 0;

        if (duration <= 0) {
          return;
        }

        player.currentTime = (Number(seekInput.value) / 100) * duration;
        syncTimeState();
        handleActivity();
      });
    }

    if (volumeInput instanceof HTMLInputElement) {
      volumeInput.addEventListener("input", () => {
        const volume = Math.min(1, Math.max(0, Number(volumeInput.value)));
        player.volume = volume;
        player.muted = volume === 0;
        syncVolumeState();
        handleActivity();
      });
    }

    player.addEventListener("click", () => {
      root.focus({ preventScroll: true });
      handleActivity();
      void togglePlayback();
    });
    root.addEventListener("mousemove", handleActivity);
    root.addEventListener("mouseenter", () => {
      root.focus({ preventScroll: true });
      handleActivity();
    });
    root.addEventListener("mouseleave", () => {
      if (!player.paused) {
        hideControls();
      }
    });
    root.addEventListener("touchstart", handleActivity, { passive: true });
    root.addEventListener("keydown", handleKeyboardShortcut);
    player.addEventListener("loadedmetadata", applyAspect);
    player.addEventListener("loadedmetadata", syncTimeState);
    player.addEventListener("play", syncPlayState);
    player.addEventListener("pause", syncPlayState);
    player.addEventListener("timeupdate", syncTimeState);
    player.addEventListener("durationchange", syncTimeState);
    player.addEventListener("volumechange", syncVolumeState);
    document.addEventListener("fullscreenchange", () => {
      handleActivity();
    });

    if (player.readyState >= 1) {
      applyAspect();
      syncTimeState();
    }

    syncPlayState();
    syncVolumeState();
    showControls();
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

function mountAdSlotBrowser() {
  const root = document.querySelector("[data-ad-slot-browser]");

  if (!(root instanceof HTMLElement)) {
    return;
  }

  const selector = root.querySelector("[data-ad-slot-selector]");
  const summary = root.querySelector("[data-ad-slot-summary]");
  const panels = document.querySelectorAll("[data-ad-slot-panel]");

  if (!(selector instanceof HTMLSelectElement) || !(summary instanceof HTMLElement) || panels.length === 0) {
    return;
  }

  const updateActiveSlot = () => {
    const activeId = selector.value;

    panels.forEach((panel) => {
      if (!(panel instanceof HTMLElement)) {
        return;
      }

      const matches = panel.dataset.adSlotPanel === activeId;
      panel.hidden = !matches;
      panel.style.display = matches ? "" : "none";
    });

    const activePanel = Array.from(panels).find((panel) => panel instanceof HTMLElement && panel.dataset.adSlotPanel === activeId);
    const titleNode = summary.querySelector("strong");
    const descriptionNode = summary.querySelector("p");

    if (!(activePanel instanceof HTMLElement)) {
      return;
    }

    if (titleNode instanceof HTMLElement) {
      titleNode.textContent = activePanel.dataset.adSlotTitle || "Ad slot";
    }

    if (descriptionNode instanceof HTMLElement) {
      descriptionNode.textContent = activePanel.dataset.adSlotDescription || "Manage one ad slot at a time.";
    }
  };

  selector.addEventListener("change", updateActiveSlot);
  updateActiveSlot();
}

function mountAdEditors() {
  const forms = document.querySelectorAll("[data-ad-editor]");

  if (forms.length === 0) {
    return;
  }

  const syncGroups = (form) => {
    const selector = form.querySelector("[data-ad-type-selector]");

    if (!(selector instanceof HTMLSelectElement)) {
      return;
    }

    const activeType = selector.value;
    const groups = form.querySelectorAll("[data-ad-type-group]");

    groups.forEach((group) => {
      if (!(group instanceof HTMLElement)) {
        return;
      }

      const matches = group.dataset.adTypeGroup === activeType;
      group.hidden = !matches;
      group.style.display = matches ? "" : "none";
    });
  };

  forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const selector = form.querySelector("[data-ad-type-selector]");

    if (!(selector instanceof HTMLSelectElement)) {
      return;
    }

    syncGroups(form);
    selector.addEventListener("change", () => syncGroups(form));
  });
}

mountCatalog();
mountWatchMedia();
mountPublicShell();
mountCookieNotice();
mountAgeGate();
mountAdminMediaForms();
mountCopyEditor();
mountAdSlotBrowser();
mountAdEditors();

// --- TACTICAL UI ENHANCEMENTS (v1.0.4) ---

/**
 * Tactical Toast Notification System
 */
window.toast = function(title, message, duration = 5000) {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `
    <span class="toast__title">${title}</span>
    <span class="toast__message">${message}</span>
  `;

  container.appendChild(toast);

  const close = () => {
    toast.classList.add('toast--closing');
    setTimeout(() => toast.remove(), 300);
  };

  toast.onclick = close;
  setTimeout(close, duration);
};

/**
 * Admin Live Search
 * Automatically filters cards/rows based on search input
 */
document.addEventListener('DOMContentLoaded', () => {
  const adminSearch = document.querySelector('.admin-toolbar input[type="search"]');
  const libraryGrid = document.querySelector('.admin-library-grid');
  const worklist = document.querySelector('.admin-worklist');

  if (adminSearch && (libraryGrid || worklist)) {
    adminSearch.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      const items = (libraryGrid || worklist).querySelectorAll('article');

      items.forEach(item => {
        const text = item.innerText.toLowerCase();
        if (text.includes(term)) {
          item.style.display = '';
          item.style.animation = 'toast-in 0.3s ease forwards';
        } else {
          item.style.display = 'none';
        }
      });
    });
  }

  // Show a welcome toast if on overview
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('screen') === 'overview' || !urlParams.get('screen')) {
    setTimeout(() => {
      window.toast('System Online', 'VIDEW 1.0.4 Command Center is ready.');
    }, 1000);
  }
});
