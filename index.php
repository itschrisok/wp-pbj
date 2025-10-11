<?php get_header(); ?>

<main>
  <h1>Welcome to Project Baldwin</h1>
  <p>This is the custom theme index. Posts and content will appear here.</p>

  <?php
    if (have_posts()) :
      while (have_posts()) : the_post();
        the_title('<h2>', '</h2>');
        the_content();
      endwhile;
    else :
      echo '<p>No content found</p>';
    endif;
  ?>
</main>

<?php get_footer(); ?>