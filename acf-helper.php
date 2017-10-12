<?php

function get_i18n_key($key)
{
  if (!$key) return null;
  return get_locale() . '_' . $key;
}

function get_i18n_field($key, $post_id = null)
{
  $value = get_field(get_i18n_key($key), $post_id);

  if (!$value)
  {
    $alt = get_field('alternate_language', $post_id);
    if ($alt && $alt !== get_locale())
    {
      $value = get_field($alt . '_' . $key, $post_id);
    }
  }

  return $value;
}

function the_i18n_field($key, $post_id = null)
{
  echo get_i18n_field($key, $post_id);
}

function get_i18n_sub_field($key)
{
  return get_sub_field(get_i18n_key($key));
}

function the_i18n_sub_field($key)
{
  echo get_i18n_sub_field($key);
}

function get_i18n_term_field($key, $term_id, $taxonomy)
{
  $value = get_field(get_i18n_key($key), "{$taxonomy}_{$term_id}");

  if (!$value)
  {
    $term = get_term($term_id, $taxonomy);
    if ($term && !is_wp_error($term)) $value = isset($term->{$key}) ? $term->{$key} : $term->name;
  }

  return $value;
}

function the_i18n_term_field($key, $term_id, $taxonomy)
{
  echo get_i18n_term_field($key, $term_id, $taxonomy);
}
