<?php
// CHILD breadcrumb override — SAFE RECOVERY VERSION

function wpst_breadcrumbs() {
    echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
    echo '<a href="' . esc_url( home_url('/') ) . '">Home</a>';
    echo '</div></div></div>';
}
