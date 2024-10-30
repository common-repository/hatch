<?php

class HatchImage
{
    private $attachment_id;

    public function __construct($attachment_id)
    {
        $this->attachment_id = $attachment_id;
    }
    
    /**
     * Return an image element of the given size
     *
     * @param  mixed $size
     * @param  mixed $icon
     * @param  mixed $attr
     * @return void
     */
    public function img($size = 'thumbnail', $icon = false, $attr = false)
    {
        return wp_get_attachment_image($this->attachment_id, $size, $icon, $attr);
    }

    /**
     * Get the source URL of the image
     *
     * @return string
     */
    public function src($size = 'thumbnail')
    {
        $img =  wp_get_attachment_image_src($this->attachment_id, $size, false);

        return $img[0];
    }

    /**
     * Get the image path on the disk
     * @param  mixed $size  The size of the image
     * @return string      The path to the image
     */
    public function path($size = 'thumbnail')
    {
        return get_attached_file($this->attachment_id, $size);
    }

    
    /**
     * Get the alt text for this image
     *
     * @return string
     */
    public function alt()
    {
        return get_post_meta($this->attachment_id, '_wp_attachment_image_alt', true);
    }
}
