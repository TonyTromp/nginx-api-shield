<?php
/**
 * Post Filter Class
 * Handles filtering of posts based on various criteria
 */

class PRT_Gist_Post_Filter {
    
    /**
     * Get all published posts
     *
     * @return array Array of post objects
     */
    public function filter_all() {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        return $this->get_formatted_posts($args);
    }
    
    /**
     * Get posts modified since a specific date
     *
     * @param string $date Date in Y-m-d format
     * @return array Array of post objects
     */
    public function filter_by_date($date) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'after' => $date,
                    'inclusive' => true
                )
            ),
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        return $this->get_formatted_posts($args);
    }
    
    /**
     * Get posts with a specific tag
     *
     * @param int $tag_id Tag ID
     * @return array Array of post objects
     */
    public function filter_by_tag($tag_id) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tag_id' => $tag_id,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        return $this->get_formatted_posts($args);
    }
    
    /**
     * Get posts and format them with required data
     *
     * @param array $args WP_Query arguments
     * @return array Array of formatted post data
     */
    private function get_formatted_posts($args) {
        $query = new WP_Query($args);
        $posts = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Get post tags
                $tags = get_the_tags($post_id);
                $tag_names = array();
                if ($tags && !is_wp_error($tags)) {
                    foreach ($tags as $tag) {
                        $tag_names[] = $tag->name;
                    }
                }
                
                // Get post categories
                $categories = get_the_category($post_id);
                $category_names = array();
                if ($categories && !is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        $category_names[] = $category->name;
                    }
                }
                
                // Format post data
                $posts[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'content_html' => apply_filters('the_content', get_the_content()),
                    'excerpt' => get_the_excerpt(),
                    'permalink' => get_permalink(),
                    'date_published' => get_the_date('Y-m-d H:i:s'),
                    'date_modified' => get_the_modified_date('Y-m-d H:i:s'),
                    'author' => get_the_author(),
                    'tags' => $tag_names,
                    'categories' => $category_names,
                    'featured_image' => get_the_post_thumbnail_url($post_id, 'full')
                );
            }
            wp_reset_postdata();
        }
        
        return $posts;
    }
    
    /**
     * Get posts based on filter type and value
     *
     * @param string $filter_type Type of filter (all, date, tag)
     * @param mixed $filter_value Filter value
     * @return array Array of formatted post data
     */
    public function get_posts($filter_type, $filter_value = '') {
        switch ($filter_type) {
            case 'date':
                return $this->filter_by_date($filter_value);
            
            case 'tag':
                return $this->filter_by_tag($filter_value);
            
            case 'all':
            default:
                return $this->filter_all();
        }
    }
}

