import type { DateStatus } from '@/components/status-badge';

export type Client = {
    id: number;
    company_name: string;
    contact_name: string | null;
    phone: string | null;
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
    category: string | null;
    default_interval_months: number | null;
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
    asset: Pick<Asset, 'id' | 'serial_number' | 'location_desc'>;
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
        client: Pick<Client, 'id' | 'is_active' | 'entered_in_error_at'>;
    };
};

export type Certificate = {
    id: number;
    site_id: number;
    asset_id: number | null;
    certificate_type: string;
    issue_date: string;
    expiry_date: string;
    issued_by: string | null;
    document: ManagedFile | null;
    status: DateStatus;
    entered_in_error_at: string | null;
    site: CertificateSite;
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
    | 'technicians',
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
        | 'asset_type_id'
        | 'equipment_status'
        | 'record_state'
        | 'sort',
        string
    >
>;
export type UpcomingInspection = {
    assetId: number;
    serialNumber: string | null;
    locationDesc: string | null;
    nextServiceDue: string;
    serviceStatus: DateStatus;
    typeName: string;
    siteName: string;
};
export type ExpiringCertificate = {
    certificateId: number;
    certificateType: string;
    expiryDate: string;
    status: DateStatus;
    siteName: string;
};
export type DashboardSummary = {
    asOfDate: string;
    stats: {
        activeClients: number;
        totalAssets: number;
        overdueInspections: number;
        expiringCerts: number;
    };
    upcomingDates: string[];
    complianceRate: {
        pass: number;
        fail: number;
        needs_repair: number;
        total: number;
    };
};

export type CalendarDueEvent = {
    id: string;
    source: 'asset' | 'inspection';
    dueDate: string;
    typeName: string;
    siteName: string;
};

export type DashboardCalendar = {
    month: string;
    events: Paginated<CalendarDueEvent>;
};

export type DashboardFilters = Partial<
    Record<
        | 'attention_from'
        | 'attention_to'
        | 'attention_page'
        | 'cert_from'
        | 'cert_to'
        | 'cert_page'
        | 'calendar_month'
        | 'calendar_page',
        string | number
    >
>;
