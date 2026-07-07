import { LeafletMap } from '@/components/maps/leaflet-map';
import { type GeoJsonPolygon } from '@/types';
import L from 'leaflet';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';
import { useEffect, useRef } from 'react';
import { useMap } from 'react-leaflet';

interface ZonePolygonEditorProps {
    value: GeoJsonPolygon | null;
    onChange: (polygon: GeoJsonPolygon | null) => void;
}

function DrawControl({ value, onChange }: ZonePolygonEditorProps) {
    const map = useMap();
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;
    // Only the initial polygon is rendered from props; afterwards
    // leaflet-draw's feature group owns the shape.
    const initialValue = useRef(value);

    useEffect(() => {
        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        if (initialValue.current !== null) {
            L.geoJSON(initialValue.current).eachLayer((layer) => drawnItems.addLayer(layer));
            map.fitBounds(drawnItems.getBounds(), { padding: [24, 24] });
        }

        const control = new L.Control.Draw({
            position: 'topright',
            draw: {
                polygon: { allowIntersection: false, showArea: false },
                polyline: false,
                rectangle: false,
                circle: false,
                marker: false,
                circlemarker: false,
            },
            edit: { featureGroup: drawnItems },
        });
        map.addControl(control);

        const emit = () => {
            const layer = drawnItems.getLayers()[0];

            if (layer === undefined) {
                onChangeRef.current(null);

                return;
            }

            onChangeRef.current((layer as L.Polygon).toGeoJSON().geometry as GeoJsonPolygon);
        };

        const onCreated = (event: L.LeafletEvent) => {
            // One polygon per zone: a new drawing replaces the old shape.
            drawnItems.clearLayers();
            drawnItems.addLayer((event as L.DrawEvents.Created).layer);
            emit();
        };

        map.on(L.Draw.Event.CREATED, onCreated);
        map.on(L.Draw.Event.EDITED, emit);
        map.on(L.Draw.Event.DELETED, emit);

        return () => {
            map.off(L.Draw.Event.CREATED, onCreated);
            map.off(L.Draw.Event.EDITED, emit);
            map.off(L.Draw.Event.DELETED, emit);
            map.removeControl(control);
            map.removeLayer(drawnItems);
        };
    }, [map]);

    return null;
}

export function ZonePolygonEditor({ value, onChange }: ZonePolygonEditorProps) {
    return (
        <LeafletMap className="h-[28rem]">
            <DrawControl value={value} onChange={onChange} />
        </LeafletMap>
    );
}
