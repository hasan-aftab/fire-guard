import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export interface Column<T> {
    key: string;
    header: string;
    className?: string;
    render: (item: T, index: number) => ReactNode;
}

interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    startIndex?: number;
    onRowClick?: (item: T) => void;
    emptyMessage?: string;
    rowKey: (item: T) => string | number;
    scrollLabel?: string;
}

export function DataTable<T>({
    columns,
    data,
    startIndex = 0,
    onRowClick,
    emptyMessage = 'No results found.',
    rowKey,
    scrollLabel = 'Scrollable data table',
}: DataTableProps<T>) {
    return (
        <Table
            containerProps={{
                className: 'custom-scrollbar',
                role: 'region',
                'aria-label': scrollLabel,
                tabIndex: 0,
            }}
        >
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    {columns.map((col) => (
                        <TableHead key={col.key} className={col.className}>
                            {col.header}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 ? (
                    <TableRow>
                        <TableCell
                            colSpan={columns.length}
                            className="h-32 text-center"
                        >
                            <p className="text-sm text-muted-foreground">
                                {emptyMessage}
                            </p>
                        </TableCell>
                    </TableRow>
                ) : (
                    data.map((item, idx) => (
                        <TableRow
                            key={rowKey(item)}
                            onClick={() => onRowClick?.(item)}
                            className={onRowClick ? 'cursor-pointer' : ''}
                        >
                            {columns.map((col) => (
                                <TableCell
                                    key={col.key}
                                    className={col.className}
                                >
                                    {col.render(item, startIndex + idx)}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))
                )}
            </TableBody>
        </Table>
    );
}
