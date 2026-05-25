(function () {
  function clampNumber(value, fallback) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
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
      }
    };
  };
})();
