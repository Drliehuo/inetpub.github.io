<?php
$total = $total ?? 0;
$perPage = $perPage ?? 10;
$currentPage = $currentPage ?? 1;
$baseUrl = $baseUrl ?? '?';
$totalPages = ceil($total / $perPage);
if ($totalPages <= 1) return;
$prev = max(1, $currentPage - 1);
$next = min($totalPages, $currentPage + 1);
$start = max(1, $currentPage - 2);
$end = min($totalPages, $currentPage + 2);
if ($end - $start < 4) {
    if ($start > 1) $start = max(1, $end - 4);
    if ($end < $totalPages) $end = min($totalPages, $start + 4);
}
?>
<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a href="<?php echo $baseUrl . 'page=1'; ?>" class="pagination-link">First</a>
        <a href="<?php echo $baseUrl . 'page=' . $prev; ?>" class="pagination-link">Prev</a>
    <?php else: ?>
        <span class="pagination-link disabled">First</span>
        <span class="pagination-link disabled">Prev</span>
    <?php endif; ?>
    <?php if ($start > 1): ?><span class="pagination-link">...</span><?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i == $currentPage): ?>
            <span class="pagination-link active"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="<?php echo $baseUrl . 'page=' . $i; ?>" class="pagination-link"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $totalPages): ?><span class="pagination-link">...</span><?php endif; ?>
    <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo $baseUrl . 'page=' . $next; ?>" class="pagination-link">Next</a>
        <a href="<?php echo $baseUrl . 'page=' . $totalPages; ?>" class="pagination-link">Last</a>
    <?php else: ?>
        <span class="pagination-link disabled">Next</span>
        <span class="pagination-link disabled">Last</span>
    <?php endif; ?>
    <span class="pagination-info"><?php echo $total; ?> items, Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
</div>