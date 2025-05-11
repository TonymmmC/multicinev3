<div class="carhome-cinema-selector">
    <div class="container">
        <div class="carhome-selector-wrapper">
            <span class="carhome-donde">¿Dónde?</span>
            <select class="carhome-form-control" id="cineSelectorHome" onchange="cambiarCine(this.value)">
                <option value="0" <?php echo ($cineSeleccionado == 0) ? 'selected' : ''; ?>>Todos los cines</option>
                <?php foreach ($listaCines as $cine): ?>
                    <option value="<?php echo $cine['id']; ?>" <?php echo ($cineSeleccionado == $cine['id']) ? 'selected' : ''; ?>>
                        <?php echo $cine['nombre']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>