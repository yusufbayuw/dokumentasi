<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ state: $wire.$entangle('{{ $getStatePath() }}') }">
        @php
        $floorImage = $getFloorImage(); // Retrieve the image URL dynamically
        @endphp

        @if ($floorImage)
        <div id="floor-map-{{ $getId() }}" style="height: 400px; position: relative;"></div>

        <input type="hidden" x-model="state" name="{{ $getStatePath() }}" id="floor-coordinates-{{ $getId() }}"
            value="{{ $getState() }}" />

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const mapId = 'floor-map-{{ $getId() }}';
                const inputId = 'floor-coordinates-{{ $getId() }}';

                const map = L.map(mapId, {
                    crs: L.CRS.Simple,
                    minZoom: -1,
                    maxZoom: 1,
                });

                const bounds = [[0, 0], [1000, 1000]]; // Adjust based on your image dimensions
                const image = L.imageOverlay('{{ asset("storage/".$floorImage) }}', bounds).addTo(map);
                map.fitBounds(bounds);

                let marker;
                map.on('click', function (event) {
                    const coordinates = event.latlng;

                    if (marker) {
                        marker.setLatLng(coordinates);
                    } else {
                        marker = L.marker(coordinates).addTo(map);
                    }

                    document.getElementById(inputId).value = JSON.stringify([coordinates.lat, coordinates.lng]);
                });
            });
        </script>
        <p>{{$getState()}}</p>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        @else
        <p>Please select a floor to display the map.</p>
        @endif
    </div>
</x-dynamic-component>