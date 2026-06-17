(function () {
  const SEARCH_STYLE_ID = 'jobfind-map-search-style';

  function clampNumber(value, fallback) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  }

  function ensureSearchStyles() {
    if (document.getElementById(SEARCH_STYLE_ID)) {
      return;
    }

    const style = document.createElement('style');
    style.id = SEARCH_STYLE_ID;
    style.textContent = [
      '.jobfind-map-search{position:absolute;top:14px;left:50%;z-index:1000;width:min(440px,calc(100% - 28px));transform:translateX(-50%);font-family:inherit;}',
      '.jobfind-map-search-form{display:grid;grid-template-columns:40px minmax(0,1fr) auto;align-items:center;min-height:46px;border:1px solid rgba(15,23,42,.14);border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.18);overflow:hidden;}',
      '.jobfind-map-search-icon{display:flex;align-items:center;justify-content:center;color:#64748b;font-size:16px;}',
      '.jobfind-map-search-input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#0f172a;font:600 14px/1.3 inherit;}',
      '.jobfind-map-search-input::placeholder{color:#64748b;font-weight:500;}',
      '.jobfind-map-search-button{height:34px;margin-right:6px;border:0;border-radius:6px;background:#4f46e5;color:#fff;padding:0 12px;font:700 12px/1 inherit;cursor:pointer;}',
      '.jobfind-map-search-button:hover{background:#4338ca;}',
      '.jobfind-map-search-button:disabled{background:#94a3b8;cursor:wait;}',
      '.jobfind-map-search-results{display:none;margin-top:8px;max-height:240px;overflow:auto;border:1px solid rgba(15,23,42,.12);border-radius:8px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.22);}',
      '.jobfind-map-search-results.active{display:block;}',
      '.jobfind-map-search-result{display:block;width:100%;border:0;border-bottom:1px solid #e2e8f0;background:#fff;padding:10px 12px;text-align:left;color:#0f172a;font:600 13px/1.35 inherit;cursor:pointer;}',
      '.jobfind-map-search-result:last-child{border-bottom:0;}',
      '.jobfind-map-search-result:hover,.jobfind-map-search-result:focus{background:#eef2ff;outline:0;}',
      '.jobfind-map-search-result small{display:block;margin-top:3px;color:#64748b;font:500 12px/1.35 inherit;}',
      '.jobfind-map-search-message{padding:11px 12px;color:#64748b;font:600 13px/1.35 inherit;}',
      '@media(max-width:640px){.jobfind-map-search{top:10px;width:calc(100% - 20px);}.jobfind-map-search-form{grid-template-columns:36px minmax(0,1fr) auto;min-height:42px;}.jobfind-map-search-button{height:30px;padding:0 10px;}.jobfind-map-search-results{max-height:190px;}}'
    ].join('');
    document.head.appendChild(style);
  }

  function createSearchControl(map, element, options, setMarker, fitCircle) {
    if (options.search === false) {
      return function () {};
    }

    ensureSearchStyles();
    element.querySelectorAll('.jobfind-map-search').forEach(function (node) {
      node.remove();
    });

    const search = document.createElement('div');
    search.className = 'jobfind-map-search';

    const form = document.createElement('form');
    form.className = 'jobfind-map-search-form';
    form.setAttribute('role', 'search');

    const icon = document.createElement('span');
    icon.className = 'jobfind-map-search-icon';
    icon.setAttribute('aria-hidden', 'true');
    const iconGlyph = document.createElement('i');
    iconGlyph.className = 'bi bi-search';
    icon.appendChild(iconGlyph);

    const input = document.createElement('input');
    input.className = 'jobfind-map-search-input';
    input.type = 'search';
    input.autocomplete = 'off';
    input.placeholder = options.searchPlaceholder;
    input.setAttribute('aria-label', options.searchPlaceholder);

    const button = document.createElement('button');
    button.className = 'jobfind-map-search-button';
    button.type = 'submit';
    button.textContent = options.searchButtonText;

    const results = document.createElement('div');
    results.className = 'jobfind-map-search-results';

    form.appendChild(icon);
    form.appendChild(input);
    form.appendChild(button);
    search.appendChild(form);
    search.appendChild(results);
    element.appendChild(search);

    if (window.L && L.DomEvent) {
      L.DomEvent.disableClickPropagation(search);
      L.DomEvent.disableScrollPropagation(search);
    }

    let debounceTimer = null;
    let activeController = null;

    function setResultsVisible(isVisible) {
      results.classList.toggle('active', isVisible);
    }

    function clearResults() {
      results.replaceChildren();
      setResultsVisible(false);
    }

    function showMessage(message) {
      results.replaceChildren();
      const item = document.createElement('div');
      item.className = 'jobfind-map-search-message';
      item.textContent = message;
      results.appendChild(item);
      setResultsVisible(true);
    }

    function formatResultName(result) {
      const address = result.address || {};
      return address.name || address.road || address.suburb || address.city || address.town || address.village || result.name || result.display_name;
    }

    function renderResults(items) {
      results.replaceChildren();

      if (!items.length) {
        showMessage(options.noResultsText);
        return;
      }

      items.forEach(function (item) {
        const lat = Number(item.lat);
        const lng = Number(item.lon);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return;
        }

        const resultButton = document.createElement('button');
        resultButton.type = 'button';
        resultButton.className = 'jobfind-map-search-result';

        const name = document.createElement('span');
        name.textContent = formatResultName(item);

        const detail = document.createElement('small');
        detail.textContent = item.display_name || '';

        resultButton.appendChild(name);
        resultButton.appendChild(detail);
        resultButton.addEventListener('click', function () {
          setMarker(lat, lng);
          input.value = item.display_name || formatResultName(item);
          clearResults();

          if (options.showCircle) {
            fitCircle();
          } else {
            map.setView([lat, lng], Math.max(map.getZoom(), 15));
          }
        });

        results.appendChild(resultButton);
      });

      setResultsVisible(results.children.length > 0);
    }

    function searchPlaces(query) {
      const trimmed = query.trim();
      if (trimmed.length < 2) {
        clearResults();
        return;
      }

      if (activeController) {
        activeController.abort();
      }

      const controller = new AbortController();
      activeController = controller;
      button.disabled = true;
      showMessage(options.loadingText);

      const params = new URLSearchParams({
        format: 'jsonv2',
        q: trimmed,
        limit: '6',
        addressdetails: '1',
        'accept-language': options.searchLanguage
      });

      if (options.searchCountryCodes) {
        params.set('countrycodes', options.searchCountryCodes);
      }

      fetch('https://nominatim.openstreetmap.org/search?' + params.toString(), {
        signal: controller.signal
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Search request failed.');
          }
          return response.json();
        })
        .then(function (data) {
          renderResults(Array.isArray(data) ? data : []);
        })
        .catch(function (error) {
          if (error.name !== 'AbortError') {
            showMessage(options.errorText);
          }
        })
        .finally(function () {
          if (activeController === controller) {
            button.disabled = false;
            activeController = null;
          }
        });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      searchPlaces(input.value);
    });

    input.addEventListener('input', function () {
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () {
        searchPlaces(input.value);
      }, 700);
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        clearResults();
        input.blur();
      }
    });

    function handleDocumentClick(event) {
      if (!search.contains(event.target)) {
        clearResults();
      }
    }

    document.addEventListener('click', handleDocumentClick);

    return function () {
      window.clearTimeout(debounceTimer);
      if (activeController) {
        activeController.abort();
      }
      document.removeEventListener('click', handleDocumentClick);
      search.remove();
    };
  }

  window.createJobFindMapPicker = function (config) {
    if (!window.L) {
      throw new Error('Leaflet.js is required for the JobFind map picker.');
    }

    const element = document.getElementById(config.elementId);
    if (!element) {
      return null;
    }

    const options = {
      lat: clampNumber(config.lat, 13.7563),
      lng: clampNumber(config.lng, 100.5018),
      hasPin: !!config.hasPin,
      radiusKm: clampNumber(config.radiusKm, 30),
      showCircle: !!config.showCircle,
      search: config.search !== false,
      searchPlaceholder: config.searchPlaceholder || 'ค้นหาสถานที่',
      searchButtonText: config.searchButtonText || 'ค้นหา',
      searchLanguage: config.searchLanguage || 'th',
      searchCountryCodes: config.searchCountryCodes || 'th',
      loadingText: config.loadingText || 'กำลังค้นหา...',
      noResultsText: config.noResultsText || 'ไม่พบสถานที่ที่ค้นหา',
      errorText: config.errorText || 'ค้นหาไม่สำเร็จ กรุณาลองใหม่',
      onChange: typeof config.onChange === 'function' ? config.onChange : function () {}
    };

    const map = L.map(element).setView([options.lat, options.lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);

    let marker = null;
    let circle = null;

    function drawCircle() {
      if (!options.showCircle || !options.hasPin) return;

      if (!circle) {
        circle = L.circle([options.lat, options.lng], {
          radius: options.radiusKm * 1000,
          color: '#6366f1',
          weight: 2,
          fillColor: '#6366f1',
          fillOpacity: 0.16
        }).addTo(map);
      } else {
        circle.setLatLng([options.lat, options.lng]);
        circle.setRadius(options.radiusKm * 1000);
      }

      circle.bringToBack();
      return circle;
    }

    function fitCircle() {
      if (!circle) return;
      map.fitBounds(circle.getBounds(), {
        padding: [34, 34],
        maxZoom: 13
      });
    }

    function setMarker(lat, lng) {
      options.lat = clampNumber(lat, options.lat);
      options.lng = clampNumber(lng, options.lng);
      options.hasPin = true;

      if (!marker) {
        marker = L.marker([options.lat, options.lng], { draggable: true }).addTo(map);
        marker.on('dragend', function (event) {
          const position = event.target.getLatLng();
          setMarker(position.lat, position.lng);
        });
      } else {
        marker.setLatLng([options.lat, options.lng]);
      }

      drawCircle();
      fitCircle();
      options.onChange(options.lat, options.lng);
    }

    map.on('click', function (event) {
      setMarker(event.latlng.lat, event.latlng.lng);
    });

    if (options.hasPin) {
      setMarker(options.lat, options.lng);
    }

    const cleanupSearch = createSearchControl(map, element, options, setMarker, fitCircle);

    return {
      setRadius(radiusKm) {
        options.radiusKm = clampNumber(radiusKm, options.radiusKm);
        drawCircle();
        fitCircle();
      },
      setView(lat, lng) {
        map.setView([lat, lng], map.getZoom());
      },
      resize() {
        map.invalidateSize();
      },
      destroy() {
        cleanupSearch();
        map.remove();
      }
    };
  };
})();
