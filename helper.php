<?php

function ___($text)
{
  return __($text, I18N_CONDUCTOR_DOMAIN);
}

function __e($text)
{
  echo __($text, I18N_CONDUCTOR_DOMAIN);
}

function __x($text, $context)
{
  return _x($text, $context, I18N_CONDUCTOR_DOMAIN);
}
