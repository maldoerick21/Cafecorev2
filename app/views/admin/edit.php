<section class="edit-food">
    <h1>Menu management</h1>
    <form method="post" action="<?= url('admin/menu/save') ?>" class="edit-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
        <div class="photo-preview">
            <?php if (!empty($item['image'])): ?>
                <img id="preview" src="<?= e($item['image']) ?>" alt="">
            <?php else: ?>
                <div class="photo-empty" id="empty"><i class="fa-solid fa-image"></i><span>Image preview</span></div>
                <img id="preview" src="" alt="" hidden>
            <?php endif; ?>
        </div>
        <div class="stack">
            <label>
                Item name
                <input type="text" name="name" value="<?= e($item['name'] ?? '') ?>" required>
            </label>
            <label>
                Category
                <input type="text" name="category" value="<?= e($item['category'] ?? 'Coffee') ?>" required>
            </label>
            <label>
                Price
                <input type="number" step="0.01" min="1" name="price" value="<?= e($item['price'] ?? '') ?>" required>
            </label>
            <label>
                Image URL
                <input type="url" name="image" id="imageField" value="<?= e($item['image'] ?? '') ?>">
            </label>
            <label>
                Description
                <textarea name="description" rows="5"><?= e($item['description'] ?? '') ?></textarea>
            </label>
            <div class="toggle-row">
                <label class="check"><input type="checkbox" name="available" <?= !isset($item) || $item['available'] ? 'checked' : '' ?>> Available</label>
                <label class="check"><input type="checkbox" name="featured" <?= !empty($item['featured']) ? 'checked' : '' ?>> Featured</label>
            </div>
            <button class="btn btn-primary" type="submit">Save item</button>
        </div>
    </form>
</section>
<script>
    const field = document.getElementById('imageField');
    const preview = document.getElementById('preview');
    const empty = document.getElementById('empty');
    field?.addEventListener('input', () => {
        if (field.value) {
            preview.src = field.value;
            preview.hidden = false;
            if (empty) empty.hidden = true;
        }
    });
</script>