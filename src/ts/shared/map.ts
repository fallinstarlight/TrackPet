import type * as Leaflet from 'leaflet';
import type { LostReport, Sighting } from './models.js';
import { element, formatDate, message } from './ui.js';
declare global { interface Window { L?: typeof Leaflet; } }

export interface Coordinates { latitude: number; longitude: number; }
export class PetMap {
    private map?: Leaflet.Map;
    private points?: Leaflet.LayerGroup;
    private bounds: Leaflet.LatLngTuple[] = [];
    private initialized = false;
    constructor(containerId: string, private status: HTMLElement | null, onPick?: (coordinates: Coordinates) => void) {
        const L = window.L;
        if (!L) { message(status, 'El mapa no está disponible. Puedes usar los campos de coordenadas y el historial.', true); return; }
        this.map = L.map(containerId, { scrollWheelZoom: false, zoomControl: false }).setView([20, -100], 5);
        L.control.zoom({ zoomInTitle: 'Acercar', zoomOutTitle: 'Alejar' }).addTo(this.map);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
        }).on('tileerror', () => message(status, 'No se pudo cargar parte del mapa base. Las coordenadas y el historial siguen disponibles.', true)).addTo(this.map);
        this.points = L.layerGroup().addTo(this.map);
        if (onPick) this.map.on('click', event => onPick({ latitude: Number(event.latlng.lat.toFixed(6)), longitude: Number(event.latlng.lng.toFixed(6)) }));
        message(status, onPick ? 'Selecciona el lugar con un clic o escribe las coordenadas.' : 'El mapa muestra observaciones, no una ruta confirmada.');
    }
    private point(position: Coordinates, color: string, text: HTMLElement, accuracy?: number | null): void {
        const L = window.L; if (!L || !this.points) return;
        const latlng: Leaflet.LatLngTuple = [position.latitude, position.longitude];
        this.bounds.push(latlng);
        if (accuracy !== undefined && accuracy !== null && accuracy > 0) L.circle(latlng, { radius: accuracy, color, weight: 1, fillOpacity: .08 }).addTo(this.points);
        L.circleMarker(latlng, { radius: 8, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 }).bindPopup(text).addTo(this.points);
    }
    selected(position: Coordinates | null, accuracy?: number | null): void {
        this.points?.clearLayers(); this.bounds = [];
        if (position) {
            this.point(position, '#256c9e', element('p', `${position.latitude.toFixed(6)}, ${position.longitude.toFixed(6)}`), accuracy);
            this.map?.setView([position.latitude, position.longitude], 15);
        }
    }
    tracking(report: LostReport, sightings: Sighting[], latest: Sighting | null): void {
        this.points?.clearLayers(); this.bounds = [];
        if (report.lastSeenLatitude !== null && report.lastSeenLatitude !== undefined && report.lastSeenLongitude !== null && report.lastSeenLongitude !== undefined) {
            this.point({ latitude: report.lastSeenLatitude, longitude: report.lastSeenLongitude }, '#256c9e', element('p', `Último lugar conocido · ${formatDate(report.lostAt)}`));
        }
        const distinct = new Map(sightings.map(item => [item.sightingId, item]));
        if (latest) distinct.set(latest.sightingId, latest);
        for (const sighting of distinct.values()) {
            const content = element('div'); content.append(element('strong', formatDate(sighting.observedAt)), element('p', sighting.notes || 'Sin notas adicionales.'),
                element('p', sighting.accuracyMeters === null ? 'Precisión no especificada' : `Precisión aproximada: ${sighting.accuracyMeters} m`));
            this.point(sighting, sighting.sightingId === latest?.sightingId ? '#267861' : '#d76d24', content, sighting.accuracyMeters);
        }
        if (!this.initialized && this.bounds.length) { this.fit(); this.initialized = true; }
    }
    fit(): void {
        if (this.bounds.length && this.map && window.L) this.map.fitBounds(window.L.latLngBounds(this.bounds), { padding: [30, 30], maxZoom: 16 });
    }
}
export function coordinates(required = false): Coordinates | null {
    const latitude = document.getElementById('latitude') as HTMLInputElement;
    const longitude = document.getElementById('longitude') as HTMLInputElement;
    if (latitude.value === '' && longitude.value === '' && !required) return null;
    if (latitude.value === '' || longitude.value === '') throw new Error('Completa la latitud y la longitud.');
    const point = { latitude: latitude.valueAsNumber, longitude: longitude.valueAsNumber };
    if (!Number.isFinite(point.latitude) || !Number.isFinite(point.longitude) || Math.abs(point.latitude) > 90 || Math.abs(point.longitude) > 180) throw new Error('Revisa el rango de las coordenadas.');
    return point;
}
export function fillCoordinates(point: Coordinates): void {
    (document.getElementById('latitude') as HTMLInputElement).value = point.latitude.toFixed(6);
    (document.getElementById('longitude') as HTMLInputElement).value = point.longitude.toFixed(6);
}
export function geolocation(button: HTMLButtonElement, output: HTMLElement | null, onLocation: (position: GeolocationPosition) => void): void {
    if (!navigator.geolocation || !window.isSecureContext) { message(output, 'El GPS necesita HTTPS o localhost. Puedes seleccionar el lugar manualmente.', true); return; }
    button.disabled = true;
    message(output, 'Esperando autorización y una ubicación del navegador…');
    navigator.geolocation.getCurrentPosition(position => {
        button.disabled = false;
        if (position.coords.accuracy > 100000) { message(output, 'La ubicación es demasiado imprecisa. Selecciona el lugar manualmente.', true); return; }
        onLocation(position); message(output, 'Ubicación obtenida. Revisa el punto antes de enviar.');
    }, error => {
        button.disabled = false;
        message(output, error.code === 1 ? 'No se autorizó el GPS. Puedes seleccionar el lugar manualmente.' : 'No pudimos obtener tu ubicación. Puedes seleccionarla en el mapa o escribirla.', true);
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
}
