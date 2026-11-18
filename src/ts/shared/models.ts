export interface Page<T> { items: T[]; total: number; limit: number; offset: number; }
export interface Viewer { authenticated: boolean; userId?: string; username?: string; }
export interface Pet {
    petId: string; ownerId: string; petName: string; petSpecies: string; petColour: string;
    petPhoto: string | null; petDescription: string | null; isLost: boolean | number; reportId?: string | null;
    ownerUsername?: string; petWeight?: number | null; petHeight?: number | null; petLenght?: number | null;
}
export interface PublicUser { userId: string; firstName: string; surname: string; username: string; profilePhoto: string | null; petCount?: number; lostCount?: number; }
export interface Profile extends PublicUser { email: string; phone: string | null; pets: Pet[]; }
export interface LostReport {
    reportId: string; petId: string; ownerId: string; status: 'open' | 'recovered' | 'cancelled'; lostAt: string;
    lastSeenArea: string; details: string; lastSeenLatitude?: number | null; lastSeenLongitude?: number | null;
    createdAt: string; updatedAt: string; closedAt: string | null; closureReason?: string | null;
    petName: string; petSpecies: string; petColour: string; petPhoto: string | null; ownerUsername: string;
    sightingsCount: number; canTrack: boolean; petActive?: boolean | number;
}
export interface Sighting {
    sightingId: string; reportId: string; reporterId: string | null; latitude: number; longitude: number;
    accuracyMeters: number | null; observedAt: string; createdAt: string; source: 'manual' | 'gps' | 'ble';
    notes: string; isRetracted: boolean; reporterUsername?: string | null; petName?: string; reportStatus?: LostReport['status'];
}
export interface Tracking { report: LostReport; latestSighting: Sighting | null; sightings: Page<Sighting>; fetchedAt: string; }
