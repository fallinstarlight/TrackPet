(() => {
    const notice = document.getElementById('notice'), retry = document.getElementById('retry');
    const map = L.map('map').setView([20, -100], 5);
    const markers = L.layerGroup().addTo(map);
    const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    });
    notice.hidden = true;
    let failed = false, picking = false, selected, fitted = false;
    tiles.on('loading', () => { failed = false; });
    tiles.on('tileerror', () => {
        failed = true;
        document.getElementById('message').textContent = 'No se pudo cargar parte del mapa. Comprueba tu conexión. Las coordenadas siguen disponibles.';
        notice.hidden = retry.hidden = false;
    });
    tiles.on('load', () => { if (!failed) notice.hidden = true; });
    retry.onclick = () => tiles.redraw();
    tiles.addTo(map);
    const dot = (latlng, color) => L.circleMarker(latlng, { radius: 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 });
    window.renderPoints = (points, canPick) => {
        picking = canPick;
        markers.clearLayers();
        if (selected) { selected.remove(); selected = null; }
        const bounds = [];
        for (const p of points) {
            const pos = [p.latitude, p.longitude];
            if (!Number.isFinite(pos[0]) || !Number.isFinite(pos[1])) continue;
            const label = document.createElement('span');
            label.textContent = p.label || 'Avistamiento';
            dot(pos, '#904214').addTo(markers).bindPopup(label);
            bounds.push(pos);
        }
        if (bounds.length && (!fitted || picking)) {
            map.invalidateSize();
            map.fitBounds(bounds, {padding:[24,24], maxZoom:16, animate:false});
            fitted = true;
        }
    };
    map.on('click', e => {
        if (!picking) return;
        markers.clearLayers();
        if (selected) selected.remove();
        selected = dot(e.latlng, '#b35416').addTo(map);
        TrackPetMap.select(e.latlng.lat, e.latlng.lng);
    });
    if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize()).observe(document.getElementById('map'));
})();
