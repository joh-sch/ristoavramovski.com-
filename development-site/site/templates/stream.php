<?php

/**
 * The stream root holds post data; the feed itself is rendered by home.
 * Visiting it directly is never useful, so send people to the feed.
 */
go($site->homePage()->url(), 302);
