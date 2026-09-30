import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationProps {
    currentPage: number;
    totalItems: number;
    pageSize: number;
    onPageChange: (page: number) => void;
}

export function Pagination({
    currentPage,
    totalItems,
    pageSize,
    onPageChange,
}: PaginationProps) {
    const totalPages = Math.ceil(totalItems / pageSize);
    if (totalPages <= 1) return null;

    const start = (currentPage - 1) * pageSize + 1;
    const end = Math.min(currentPage * pageSize, totalItems);

    const getPageNumbers = (): (number | '...')[] => {
        const pages: (number | '...')[] = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
            return pages;
        }
        pages.push(1);
        if (currentPage > 3) pages.push('...');
        const rangeStart = Math.max(2, currentPage - 1);
        const rangeEnd = Math.min(totalPages - 1, currentPage + 1);
        for (let i = rangeStart; i <= rangeEnd; i++) pages.push(i);
        if (currentPage < totalPages - 2) pages.push('...');
        pages.push(totalPages);
        return pages;
    };

    return (
        <div className="flex flex-col items-center justify-between gap-2 px-3 py-2.5 sm:flex-row">
            <p className="text-xs text-muted-foreground">
                Showing{' '}
                <span className="font-medium text-foreground">
                    {start}–{end}
                </span>{' '}
                of{' '}
                <span className="font-medium text-foreground">
                    {totalItems}
                </span>
            </p>
            <div className="flex max-w-full items-center gap-0.5 overflow-x-auto">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Previous page"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                >
                    <ChevronLeft data-icon="inline-start" />
                </Button>
                {getPageNumbers().map((page, i) =>
                    page === '...' ? (
                        <span
                            key={`ellipsis-${i}`}
                            className="px-1.5 text-xs text-muted-foreground"
                        >
                            …
                        </span>
                    ) : (
                        <Button
                            key={page}
                            variant={
                                page === currentPage ? 'default' : 'outline'
                            }
                            size="icon"
                            onClick={() => onPageChange(page)}
                            aria-label={`Page ${page}`}
                            aria-current={
                                page === currentPage ? 'page' : undefined
                            }
                            className="size-7"
                        >
                            {page}
                        </Button>
                    ),
                )}
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Next page"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage === totalPages}
                >
                    <ChevronRight data-icon="inline-start" />
                </Button>
            </div>
        </div>
    );
}
