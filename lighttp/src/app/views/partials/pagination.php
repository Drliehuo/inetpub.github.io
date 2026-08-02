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
<nav aria-label="Page navigation">
    <ul class="pagination">
        <!-- 上一页 -->
        <li<?php if ($currentPage <= 1) echo ' class="disabled"'; ?>>
            <?php if ($currentPage > 1): ?>
            <a href="<?php echo $baseUrl . 'page=' . $prev; ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
            <?php else: ?>
            <span><span aria-hidden="true">&laquo;</span></span>
            <?php endif; ?>
        </li>
        <!-- 页码 -->
        <?php if ($start > 1): ?>
        <li><a href="<?php echo $baseUrl . 'page=1'; ?>">1</a></li>
        <?php if ($start > 2): ?>
        <li class="disabled"><span>…</span></li>
        <?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li<?php if ($i == $currentPage) echo ' class="active"'; ?>>
            <?php if ($i == $currentPage): ?>
            <span><?php echo $i; ?> <span class="sr-only">(current)</span></span>
            <?php else: ?>
            <a href="<?php echo $baseUrl . 'page=' . $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?>
        <li class="disabled"><span>…</span></li>
        <?php endif; ?>
        <li><a href="<?php echo $baseUrl . 'page=' . $totalPages; ?>"><?php echo $totalPages; ?></a></li>
        <?php endif; ?>
        <!-- 下一页 -->
        <li<?php if ($currentPage >= $totalPages) echo ' class="disabled"'; ?>>
            <?php if ($currentPage < $totalPages): ?>
            <a href="<?php echo $baseUrl . 'page=' . $next; ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
            <?php else: ?>
            <span><span aria-hidden="true">&raquo;</span></span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
<div class="clearfix">
    <span class="text-muted small"><?php echo $total; ?> items, Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
</div>