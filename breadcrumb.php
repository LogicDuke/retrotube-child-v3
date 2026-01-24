<?php
// Child theme override of RetroTube breadcrumb using Rank Math
if (is_singular('video')) {
  $home_link = home_url('/');
  $videos_link = home_url('/videos/');
  $delimiter = '<i class="fa fa-caret-right"></i>';

  echo '<div class="breadcrumbs-area">';
  echo '<div class="row">';
  echo '<div id="breadcrumbs">';
  echo '<a href="' . esc_url($home_link) . '">' . esc_html__('Home', 'wpst') . '</a>';
  echo '<span class="separator">' . $delimiter . '</span>';
  echo '<a href="' . esc_url($videos_link) . '">' . esc_html__('Videos', 'wpst') . '</a>';
  echo '<span class="separator">' . $delimiter . '</span>';
  echo '<span class="current">' . esc_html(get_the_title()) . '</span>';
  echo '</div>';
  echo '</div>';
  echo '</div>';
  return;
}

if (function_exists('rank_math_the_breadcrumbs')) {
  echo '<div id="breadcrumbs">';
  rank_math_the_breadcrumbs();
  echo '</div>';
}
