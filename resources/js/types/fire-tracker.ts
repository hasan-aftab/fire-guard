import type { DateStatus } from '@/components/status-badge';

export type Client = {
    id: number;
    company_name: string;
    contact_name: string | null;
    phone: string | null;
    whatsapp_phone: string | null;
    whatsapp_reminders_enabled: boolean;
    email: string | null;
    billing_address: string | null;
    notes: string | null;
    is_active: boolean;
    entered_in_error_at: string | null;
    sites?: Pick<
        Site,
        'id' | 'site_name' | 'address' | 'is_active' | 'entered_in_error_at'
    >[];
    sites_count: number;
    current_sites_count: number;
};

export type Site = {
    id: number;
    client_id: number;
    site_name: string;
    address: string;
    suburb_region: string | null;
    access_notes: string | null;
    site_contact: string | null;
    site_phone: string | null;
    is_active: boolean;
    entered_in_error_at: string | null;
    assets_count?: number;
    certificates_count?: number;
    client?: Client;
};

export type CertificateSite = Pick<
    Site,
    'id' | 'client_id' | 'site_name' | 'is_active' | 'entered_in_error_at'
> & {
    client: Pick<
        Client,
        'id' | 'company_name' | 'is_active' | 'entered_in_error_at'
    >;
};

export type AssetType = {
    id: number;
    type_name: string;
    default_interval_months: number | null;
    is_active: boolean;
    assets_count?: number;
};

export type CertificateType = {
    id: number;
    name: string;
    is_active: boolean;
    certificates_count?: number;
};

export type Asset = {
    id: number;
    site_id: number;
    asset_type_id: number;
    serial_number: string | null;
    location_desc: string | null;
    install_date: string | null;
    last_service_date: string | null;
    next_service_due: string | null;
    status: string;
    service_status: DateStatus;
    entered_in_error_at: string | null;
    inspections_count?: number;
    certificates_count?: number;
    site: Site;
    asset_type: AssetType;
};

export type Technician = {
    id: number;
    full_name: string;
    phone: string | null;
    email: string | null;
    license_number: string | null;
    license_expiry: string | null;
    license_status: 'expired' | 'expiring_soon' | 'valid' | 'unknown';
    is_active: boolean;
    entered_in_error_at: string | null;
    inspections_count?: number;
};

export type Inspection = {
    id: number;
    asset_id: number;
    technician_id: number | null;
    inspection_date: string;
    result: string;
    notes: string | null;
    next_due_date: string | null;
    photos: ManagedFile[];
    entered_in_error_at: string | null;
    asset: Pick<Asset, 'id' | 'serial_number' | 'location_desc'> & {
        site: Pick<Site, 'id' | 'site_name'> & {
            client: Pick<Client, 'id' | 'company_name'>;
        };
    };
    technician: Pick<Technician, 'id' | 'full_name'> | null;
};

export type InspectionAssetOption = Pick<
    Asset,
    | 'id'
    | 'site_id'
    | 'serial_number'
    | 'location_desc'
    | 'status'
    | 'entered_in_error_at'
> & {
    asset_type: Pick<AssetType, 'type_name' | 'default_interval_months'>;
    suggested_next_due_date: string | null;
    site: Pick<
        Site,
        'id' | 'site_name' | 'is_active' | 'entered_in_error_at'
    > & {
        client: Pick<
            Client,
            'id' | 'company_name' | 'is_active' | 'entered_in_error_at'
        >;
    };
};

export type Certificate = {
    id: number;
    site_id: number;
    asset_id: number | null;
    certificate_type_id: number;
    certificate_type: CertificateType;
    issue_date: string;
    expiry_date: string;
    issued_by: string | null;
    document: ManagedFile | null;
    status: DateStatus;
    entered_in_error_at: string | null;
    site: CertificateSite;
    asset: Pick<
        Asset,
        'id' | 'site_id' | 'serial_number' | 'location_desc'
    > | null;
};

export type ManagedFile = {
    id: number;
    original_name: string;
    mime_type: string;
    file_size: number;
};

export type FireNotification = {
    id: number;
    type:
        | 'overdue_asset'
        | 'due_soon_asset'
        | 'expiring_cert'
        | 'expired_cert'
        | 'failed_inspection';
    title: string;
    message: string;
    entity_id: number;
    entity_type: 'asset' | 'certificate' | 'inspection';
    is_read: boolean;
    created_at: string;
};

export type Counts = Record<
    | 'clients'
    | 'sites'
    | 'assets'
    | 'inspections'
    | 'certificates'
    | 'technicians'
    | 'assetTypes'
    | 'certificateTypes'
    | 'reports'
    | 'reminders',
    number
>;
export type NotificationData = {
    items: FireNotification[];
    unreadCount: number;
};
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
};
export type TrackerFilters = Partial<
    Record<
        | 'search'
        | 'status'
        | 'result'
        | 'from'
        | 'to'
        | 'issue_from'
        | 'issue_to'
        | 'active'
        | 'site_presence'
        | 'site_id'
        | 'technician_id'
        | 'asset_id'
        | 'asset_type_id'
        | 'certificate_type_id'
        | 'equipment_status'
        | 'license_status'
        | 'inspection_activity'
        | 'record_state'
        | 'dashboard_scope'
        | 'sort',
        string
    >
>;
export type DashboardSummary = {
    asOfDate: string;
    isFirstRun: boolean;
    /** Rolling window the compliance gauge counts, in months. */
    complianceMonths: number;
    /** Inclusive lower bound of the gauge window (YYYY-MM-DD). */
    complianceSince: string;
    /** Links used by setup and the compliance card. */
    links: {
        activeClients: string;
        /** Inspections inside the gauge's own window, for the pass-rate card. */
        passRateInspections: string;
    };
};

export type DashboardFilters = Partial<
    Record<
        | 'schedule_from'
        | 'schedule_to'
        | 'schedule_date_mode'
        | 'schedule_type'
        | 'schedule_horizon'
        | 'schedule_overdue'
        | 'schedule_day'
        | 'schedule_page',
        string
    >
>;

/**
 * The four dashboard cards. Each count is scoped independently of the Schedule
 * window — except `dueSoon` and `certificates`, which name the horizon preset
 * they measure so the panel can select exactly that scope when the card is used.
 */
export type DashboardKpis = {
    overdue: {
        total: number;
        byKind: Record<ScheduleItemKind, number>;
    };
    dueSoon: {
        total: number;
        horizonPreset: string;
        horizonDays: number;
    };
    certificates: {
        expired: number;
        expiringSoon: number;
        total: number;
        horizonPreset: string;
    };
    passRate: {
        percent: number;
        band: 'success' | 'warning' | 'danger';
        pass: number;
        fail: number;
        needsRepair: number;
        total: number;
    };
};

export type ScheduleStatus = 'overdue' | 'due_soon' | 'ok';

/**
 * `asset` is a record whose *asset* service date is due. The UI calls these
 * "inspections" because servicing an asset is what the visit is for, but the
 * stored record really is an asset, so the wire value stays honest.
 */
export type ScheduleItemKind = 'asset' | 'certificate';

export type ScheduleType = 'all' | ScheduleItemKind;

/**
 * What the schedule does with the overdue backlog.
 *
 * `include` is the default and shows it above the window. `only` drops the
 * forward window entirely, which is what the "Overdue" tile asks for. `exclude`
 * hides the backlog and keeps the window, which is what the "Due in 7 days"
 * tile asks for, so a tile's number and the rows it opens cannot disagree.
 */
export type ScheduleOverdueMode = 'include' | 'only' | 'exclude';

export type ScheduleItem = {
    key: string;
    kind: ScheduleItemKind;
    entityId: number;
    dueDate: string;
    isOverdue: boolean;
    /** Server-resolved; the client never recomputes it from the clock. */
    daysUntil: number;
    status: ScheduleStatus;
    typeName: string;
    siteName: string;
    clientName: string;
    reference: string | null;
    /** Where on the site the record lives; null for certificates. */
    locationDesc: string | null;
    href: string;
};

/**
 * One of the three fixed agenda sections. All three are always present and in
 * this order, so neither the view nor a test has to index around a missing
 * group; a group holding nothing has `itemCount` 0 and an empty `items`.
 */
export type ScheduleGroupId = 'overdue' | 'next' | 'later';

export type ScheduleGroup = {
    id: ScheduleGroupId;
    label: string;
    itemCount: number;
    byKind: Record<ScheduleItemKind, number>;
    /** Earliest date in the group, or null when it holds nothing. */
    firstDate: string | null;
    items: ScheduleItem[];
};

/** Always an in-window day, never an overdue one. */
export type ScheduleDay = {
    date: string;
    isToday: boolean;
    itemCount: number;
};

export type DashboardSchedule = {
    from: string;
    to: string;
    horizonDays: number;
    /** Inclusive offset from `from` to `to`, so horizonDays is this plus one. */
    horizonOffset: number;
    /** The preset this window is, or null when it is a custom range. */
    horizonPreset: string | null;
    type: ScheduleType;
    overdueMode: ScheduleOverdueMode;
    /** The day narrowed to, or null. Narrows every group at once. */
    day: string | null;
    /** True in overdue-only mode, where the strip and range are meaningless. */
    windowHidden: boolean;
    /** Offset that separates `next` from `later`. */
    groupBoundaryDays: number;
    groups: ScheduleGroup[];
    counts: Record<'all' | ScheduleItemKind, number>;
    /** One entry per day of the window, including days with no work. */
    days: ScheduleDay[];
    overdueSince: string | null;
    overdueByKind: Record<ScheduleItemKind, number>;
    /** The first thing due past the window, so the range can admit the gap. */
    nextAfter: { date: string; daysUntil: number } | null;
    overdueCount: number;
    /** In-window rows only, counted before the row cap is applied. */
    windowItemCount: number;
    totalCount: number;
    page: number;
    lastPage: number;
    returnedCount: number;
    truncated: boolean;
    maxItems: number;
    maxHorizonDays: number;
    /** Preset key to inclusive offset, so the client never hardcodes a window. */
    horizonPresets: Record<string, number>;
    defaultHorizonPreset: string;
};
