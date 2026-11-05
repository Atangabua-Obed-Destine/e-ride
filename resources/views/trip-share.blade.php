<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#E02020">
    <title>{{ $payload['business_name'] ?? 'E-Ride' }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    @verbatim
    <style>
        :root {
            --brand: #E02020;
            --brand-soft: rgba(224, 32, 32, .1);
            --bg: #F4F5F7;
            --card: #FFFFFF;
            --text: #1D2D2B;
            --muted: #6B7675;
            --line: #E6E8EB;
            --ok: #12A150;
            --ok-soft: rgba(18, 161, 80, .12);
            --shadow: 0 -8px 30px rgba(0, 0, 0, .12);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111315;
                --card: #1C1F22;
                --text: #EEF1F2;
                --muted: #9AA4A6;
                --line: #2C3135;
                --shadow: 0 -8px 30px rgba(0, 0, 0, .5);
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: var(--bg); color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { display: flex; flex-direction: column; min-height: 100%; }

        .top { position: fixed; top: 0; left: 0; right: 0; z-index: 5; display: flex; align-items: center;
            justify-content: space-between; padding: calc(12px + env(safe-area-inset-top)) 16px 12px; pointer-events: none; }
        .brand { pointer-events: auto; background: var(--card); color: var(--brand); font-weight: 800; letter-spacing: -.3px;
            padding: 8px 14px; border-radius: 999px; box-shadow: 0 2px 10px rgba(0, 0, 0, .15); font-size: 15px; }
        .live { pointer-events: auto; display: none; align-items: center; gap: 6px; background: var(--card); color: var(--text);
            padding: 8px 12px; border-radius: 999px; box-shadow: 0 2px 10px rgba(0, 0, 0, .15); font-size: 13px; font-weight: 600; }
        .live i { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); animation: pulse 1.6s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(224, 32, 32, .6); } 70% { box-shadow: 0 0 0 8px rgba(224, 32, 32, 0); } 100% { box-shadow: 0 0 0 0 rgba(224, 32, 32, 0); } }

        #map { flex: 1 1 auto; min-height: 50vh; background: var(--line); }
        .map-note { display: none; flex: 1 1 auto; min-height: 30vh; align-items: center; justify-content: center;
            color: var(--muted); font-size: 14px; padding: 80px 16px 24px; text-align: center; }

        .sheet { position: relative; z-index: 4; background: var(--card); border-radius: 22px 22px 0 0; box-shadow: var(--shadow);
            margin-top: -22px; padding: 20px 16px calc(20px + env(safe-area-inset-bottom)); width: 100%; max-width: 560px; align-self: center; }
        body.final .sheet { margin-top: 0; border-radius: 22px; margin: auto; max-width: 460px; width: calc(100% - 32px); box-shadow: 0 8px 30px rgba(0, 0, 0, .1); }
        body.final #map { display: none; }

        .kicker { font-size: 13px; color: var(--muted); margin: 0 0 4px; }
        h1 { font-size: 21px; line-height: 1.25; margin: 0; letter-spacing: -.2px; }
        .sub { margin: 6px 0 0; color: var(--muted); font-size: 15px; }
        .sub strong { color: var(--text); }
        .badge-icon { width: 56px; height: 56px; border-radius: 50%; display: none; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 14px; }
        body.final .badge-icon { display: flex; }
        .badge-icon.ok { background: var(--ok-soft); color: var(--ok); }
        .badge-icon.off { background: var(--brand-soft); color: var(--brand); }

        .driver { display: none; align-items: center; gap: 12px; margin-top: 16px; padding: 12px; border-radius: 16px; background: var(--bg); }
        .driver img.avatar { width: 48px; height: 48px; border-radius: 14px; object-fit: cover; background: var(--line); }
        .driver .initial { width: 48px; height: 48px; border-radius: 14px; background: var(--brand-soft); color: var(--brand);
            display: none; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; flex: 0 0 auto; }
        .driver .who { flex: 1; min-width: 0; }
        .driver .name { font-weight: 700; font-size: 16px; }
        .driver .car { color: var(--muted); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .plate { font-weight: 800; font-size: 14px; letter-spacing: .5px; padding: 6px 10px; border-radius: 8px;
            border: 2px solid var(--text); white-space: nowrap; }

        .route { display: none; margin-top: 14px; }
        .stop { display: flex; gap: 12px; align-items: flex-start; padding: 6px 0; }
        .dot { width: 12px; height: 12px; border-radius: 50%; margin-top: 4px; flex: 0 0 auto; }
        .dot.from { background: var(--ok); }
        .dot.to { background: var(--brand); }
        .stop .label { font-size: 12px; color: var(--muted); }
        .stop .addr { font-size: 14px; }

        .foot { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); line-height: 1.5; }
        .foot a { color: var(--brand); font-weight: 600; text-decoration: none; }
        .updated { margin-top: 4px; }

        .vehicle-marker { position: absolute; transform: translate(-50%, -50%); }
        .vehicle-marker img { display: block; width: 26px; transition: transform .6s ease; filter: drop-shadow(0 2px 3px rgba(0, 0, 0, .35)); }
    </style>
    @endverbatim
</head>
<body>
<div class="top">
    <div class="brand">{{ $payload['business_name'] ?? 'E-Ride' }}</div>
    <div class="live" id="live"><i></i><span id="liveText"></span></div>
</div>

<div id="map"></div>
<div class="map-note" id="mapNote"></div>

<main class="sheet" id="sheet">
    <div class="badge-icon" id="badge"></div>
    <p class="kicker" id="kicker"></p>
    <h1 id="title"></h1>
    <p class="sub" id="sub"></p>

    <div class="driver" id="driver">
        <img class="avatar" id="driverPhoto" alt="">
        <div class="initial" id="driverInitial"></div>
        <div class="who">
            <div class="name" id="driverName"></div>
            <div class="car" id="driverCar"></div>
        </div>
        <div class="plate" id="plate"></div>
    </div>

    <div class="route" id="route">
        <div class="stop"><span class="dot from"></span><div><div class="label" id="fromLabel"></div><div class="addr" id="fromAddr"></div></div></div>
        <div class="stop"><span class="dot to"></span><div><div class="label" id="toLabel"></div><div class="addr" id="toAddr"></div></div></div>
    </div>

    <div class="foot">
        <div id="footText"></div>
        <div class="updated" id="updated"></div>
        @if ($appUrl)
            <div style="margin-top: 8px"><a href="{{ $appUrl }}" rel="noopener" id="getApp"></a></div>
        @endif
    </div>
</main>

<script>
    window.SHARE = {
        lang: @json($lang),
        payload: @json($payload),
        dataUrl: @json(route('trip-share.data', $token)),
        mapKey: @json($mapKey),
        icons: {
            car: @json(dynamicAsset('public/assets/trip-share/car.png')),
            bike: @json(dynamicAsset('public/assets/trip-share/bike.png')),
            truck: @json(dynamicAsset('public/assets/trip-share/truck.png')),
        },
    };
</script>
@verbatim
<script>
(function () {
    const T = {
        en: {
            someone: 'your contact',
            ride_title: "{name}'s trip", move_title: "{name}'s move", parcel_title: 'Parcel for {receiver}', parcel_title_plain: 'Parcel delivery',
            waiting: 'Looking for a driver',
            coming: '{driver} is on the way to pick up {name}', coming_parcel: '{driver} is on the way to collect the parcel',
            coming_sub: 'About {min} min away',
            on_trip: '{name} is on the way', on_trip_move: "{name}'s move is on the way", on_trip_parcel: 'The parcel is on its way',
            arriving: 'Arriving around {time}',
            returning: 'The parcel is going back to the sender',
            arrived: '{name} arrived safely', arrived_move: 'Move completed', arrived_parcel: 'Parcel delivered',
            at_time: 'at {time}',
            cancelled: 'This trip was cancelled', cancelled_parcel: 'This delivery was cancelled',
            returned: 'The parcel was returned to the sender',
            expired: 'This link has expired',
            expired_sub: 'Trip links stop working 2 hours after the trip ends, or when the person stops sharing.',
            pickup: 'Pickup', destination: 'Destination', live: 'Live',
            foot: 'Shared with you through {brand}. Only people with this link can see this trip.',
            updated: 'Updated {time}', get_app: 'Get the {brand} app',
            map_off: 'The map could not be loaded. The trip details below still update.',
        },
        fr: {
            someone: 'votre proche',
            ride_title: 'Trajet de {name}', move_title: 'Déménagement de {name}', parcel_title: 'Colis pour {receiver}', parcel_title_plain: 'Livraison de colis',
            waiting: "Recherche d'un chauffeur",
            coming: '{driver} est en route pour prendre {name}', coming_parcel: '{driver} est en route pour récupérer le colis',
            coming_sub: 'À environ {min} min',
            on_trip: '{name} est en route', on_trip_move: 'Le déménagement de {name} est en route', on_trip_parcel: 'Le colis est en route',
            arriving: 'Arrivée vers {time}',
            returning: "Le colis retourne chez l'expéditeur",
            arrived: '{name} est bien arrivé(e)', arrived_move: 'Déménagement terminé', arrived_parcel: 'Colis livré',
            at_time: 'à {time}',
            cancelled: 'Ce trajet a été annulé', cancelled_parcel: 'Cette livraison a été annulée',
            returned: "Le colis a été retourné à l'expéditeur",
            expired: 'Ce lien a expiré',
            expired_sub: "Les liens de trajet ne marchent plus 2 heures après la fin du trajet, ou quand la personne arrête le partage.",
            pickup: 'Départ', destination: 'Destination', live: 'En direct',
            foot: 'Partagé avec vous via {brand}. Seules les personnes ayant ce lien peuvent voir ce trajet.',
            updated: 'Mis à jour à {time}', get_app: "Télécharger l'application {brand}",
            map_off: "La carte n'a pas pu être chargée. Les détails du trajet ci-dessous restent à jour.",
        },
    };
    const S = window.SHARE;
    const L = T[S.lang] || T.en;
    const LIVE = ['waiting', 'coming', 'on_trip', 'returning'];
    const POLL_MS = 8000;
    const $ = (id) => document.getElementById(id);
    const tr = (key, vars) => (L[key] || T.en[key] || key).replace(/\{(\w+)\}/g, (m, k) => (vars && vars[k] != null) ? vars[k] : m);
    const time = (d) => d.toLocaleTimeString(S.lang === 'fr' ? 'fr-FR' : 'en-GB', {hour: '2-digit', minute: '2-digit'});

    let data = S.payload;
    let timer = null;
    let map = null, mapReady = false, mapFailed = false, userMoved = false, fitted = '';
    let vehicle = null, pickupMarker = null, destinationMarker = null, routeLine = null;

    function show(el, on, display) { el.style.display = on ? (display || 'flex') : 'none'; }

    function render() {
        const d = data;
        const brand = d.business_name || 'E-Ride';
        const name = d.customer_name || L.someone;
        const driver = d.driver ? d.driver.name : '';
        const parcel = d.type === 'parcel', move = d.type === 'move';
        const live = LIVE.includes(d.state);
        document.body.classList.toggle('final', !live);
        show($('live'), live);
        $('liveText').textContent = L.live;

        $('kicker').textContent = parcel ? (d.receiver_name ? tr('parcel_title', {receiver: d.receiver_name}) : L.parcel_title_plain)
            : tr(move ? 'move_title' : 'ride_title', {name});
        let title = '', sub = '';
        const eta = d.eta_minutes;
        switch (d.state) {
            case 'waiting': title = L.waiting; break;
            case 'coming':
                title = tr(parcel ? 'coming_parcel' : 'coming', {driver, name});
                if (eta) sub = tr('coming_sub', {min: eta});
                break;
            case 'on_trip':
                title = tr(parcel ? 'on_trip_parcel' : move ? 'on_trip_move' : 'on_trip', {name});
                if (eta) sub = tr('arriving', {time: time(new Date(Date.now() + eta * 60000))});
                break;
            case 'returning': title = L.returning; break;
            case 'arrived':
                title = tr(parcel ? 'arrived_parcel' : move ? 'arrived_move' : 'arrived', {name});
                if (d.ended_at) sub = tr('at_time', {time: time(new Date(d.ended_at))});
                break;
            case 'cancelled': title = parcel ? L.cancelled_parcel : L.cancelled; break;
            case 'returned': title = L.returned; break;
            default: title = L.expired; sub = L.expired_sub; $('kicker').textContent = '';
        }
        $('title').textContent = title;
        $('sub').textContent = sub;
        show($('sub'), !!sub, 'block');

        const badge = $('badge');
        badge.className = 'badge-icon ' + (d.state === 'arrived' ? 'ok' : 'off');
        badge.textContent = d.state === 'arrived' ? '✓' : '!';

        const showDriver = !!d.driver && (live || d.state === 'arrived');
        show($('driver'), showDriver);
        if (showDriver) {
            // The photo, or the first letter of the name when there is none.
            const photo = $('driverPhoto'), initial = $('driverInitial');
            initial.textContent = (d.driver.name || '?').charAt(0).toUpperCase();
            photo.onerror = () => { photo.style.display = 'none'; initial.style.display = 'flex'; };
            if (d.driver.photo && photo.getAttribute('src') !== d.driver.photo) photo.src = d.driver.photo;
            if (!d.driver.photo) photo.onerror();
            $('driverName').textContent = d.driver.name || '';
            const v = d.vehicle || {};
            $('driverCar').textContent = [v.model, v.category].filter(Boolean).join(' · ');
            $('plate').textContent = v.plate || '';
            show($('plate'), !!v.plate, 'block');
        }

        const showRoute = d.state !== 'expired' && (d.pickup || d.destination);
        show($('route'), showRoute, 'block');
        if (showRoute) {
            $('fromLabel').textContent = L.pickup;
            $('fromAddr').textContent = d.pickup && d.pickup.address || '';
            $('toLabel').textContent = L.destination;
            $('toAddr').textContent = d.destination && d.destination.address || '';
        }

        $('footText').textContent = tr('foot', {brand});
        $('updated').textContent = live ? tr('updated', {time: time(new Date())}) : '';
        if ($('getApp')) $('getApp').textContent = tr('get_app', {brand});
        document.title = $('kicker').textContent || brand;

        if (live) drawMap();
    }

    // ---- Map ----
    function loadMap() {
        if (!S.mapKey || map || mapFailed) return;
        window.gm_authFailure = mapUnavailable;
        window.__shareMapReady = () => { mapReady = true; drawMap(); };
        const s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(S.mapKey) + '&libraries=geometry&callback=__shareMapReady&loading=async&language=' + S.lang;
        s.async = true;
        s.onerror = mapUnavailable;
        document.head.appendChild(s);
    }

    function mapUnavailable() {
        mapFailed = true;
        $('map').style.display = 'none';
        $('mapNote').textContent = L.map_off;
        show($('mapNote'), LIVE.includes(data.state));
    }

    function vehicleIcon(type) {
        if (type === 'motor_bike') return S.icons.bike;
        if (['tricycle', 'pickup', 'dyna', 'truck'].includes(type)) return S.icons.truck;
        return S.icons.car;
    }

    // A rotatable vehicle image (Google's plain markers cannot rotate an image).
    function VehicleMarker(position, icon) {
        this.position = position; this.heading = 0;
        this.div = document.createElement('div');
        this.div.className = 'vehicle-marker';
        this.img = document.createElement('img');
        this.img.src = icon; this.img.alt = '';
        this.div.appendChild(this.img);
    }

    function defineVehicleMarker() {
        VehicleMarker.prototype = new google.maps.OverlayView();
        VehicleMarker.prototype.onAdd = function () { this.getPanes().markerLayer.appendChild(this.div); };
        VehicleMarker.prototype.draw = function () {
            const p = this.getProjection().fromLatLngToDivPixel(this.position);
            if (p) { this.div.style.left = p.x + 'px'; this.div.style.top = p.y + 'px'; }
        };
        VehicleMarker.prototype.onRemove = function () { this.div.remove(); };
        VehicleMarker.prototype.moveTo = function (position) {
            const from = this.position;
            if (google.maps.geometry.spherical.computeDistanceBetween(from, position) > 8) {
                this.heading = google.maps.geometry.spherical.computeHeading(from, position);
                this.img.style.transform = 'rotate(' + this.heading + 'deg)';
            }
            this.position = position;
            this.draw();
        };
    }

    function pin(color) {
        return {path: google.maps.SymbolPath.CIRCLE, scale: 8, fillColor: color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 3};
    }

    function drawMap() {
        if (mapFailed) return;
        if (!mapReady) { loadMap(); return; }
        const d = data;
        const ll = (p) => p ? new google.maps.LatLng(p.lat, p.lng) : null;
        if (!map) {
            defineVehicleMarker();
            const center = ll(d.position) || ll(d.pickup) || new google.maps.LatLng(5.96, 10.15);
            map = new google.maps.Map($('map'), {
                center, zoom: 15, disableDefaultUI: true, zoomControl: true, gestureHandling: 'greedy', clickableIcons: false,
            });
            map.addListener('dragstart', () => { userMoved = true; });
        }

        // Coming: the driver and the pickup. On the trip: the driver, the route and the destination.
        const target = d.state === 'on_trip' ? d.destination : (d.state === 'returning' ? null : d.pickup);
        const targetMarker = d.state === 'on_trip' ? 'destination' : 'pickup';
        if (pickupMarker) pickupMarker.setMap(null);
        if (destinationMarker) destinationMarker.setMap(null);
        pickupMarker = destinationMarker = null;
        if (target) {
            const m = new google.maps.Marker({position: ll(target), map, icon: pin(targetMarker === 'pickup' ? '#12A150' : '#E02020')});
            if (targetMarker === 'pickup') pickupMarker = m; else destinationMarker = m;
        }

        const path = d.route ? google.maps.geometry.encoding.decodePath(d.route) : [];
        if (!routeLine) routeLine = new google.maps.Polyline({map, strokeColor: '#E02020', strokeOpacity: .85, strokeWeight: 5});
        routeLine.setPath(path);

        const pos = ll(d.position);
        if (pos) {
            if (!vehicle) {
                vehicle = new VehicleMarker(pos, vehicleIcon(d.vehicle && d.vehicle.type));
                vehicle.setMap(map);
            } else {
                vehicle.moveTo(pos);
            }
        }

        // Frame the driver and where they're heading once per stage, unless the viewer moved the map.
        if (fitted !== d.state && !userMoved) {
            const bounds = new google.maps.LatLngBounds();
            [pos, ll(target)].filter(Boolean).forEach((p) => bounds.extend(p));
            path.forEach((p) => bounds.extend(p));
            if (!bounds.isEmpty()) {
                map.fitBounds(bounds, {top: 80, bottom: 40, left: 40, right: 40});
                google.maps.event.addListenerOnce(map, 'idle', () => { if (map.getZoom() > 17) map.setZoom(17); });
            }
            fitted = d.state;
        } else if (pos && !userMoved && !map.getBounds()?.contains(pos)) {
            map.panTo(pos);
        }
    }

    // ---- Updates ----
    async function poll() {
        try {
            const r = await fetch(S.dataUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (r.ok) {
                const body = await r.json();
                if (body && body.data) data = body.data;
                render();
            }
        } catch (e) { /* offline for a moment: try again next time */ }
        schedule();
    }

    function schedule() {
        clearTimeout(timer);
        if (LIVE.includes(data.state) && !document.hidden) timer = setTimeout(poll, POLL_MS);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && LIVE.includes(data.state)) poll(); else clearTimeout(timer);
    });

    render();
    schedule();
})();
</script>
@endverbatim
</body>
</html>
