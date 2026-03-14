<?php
/**
 * Per-request random key generator for the promotional slot.
 * Generates random element IDs and CSS class names once per PHP process,
 * then caches them so all classes (enqueue, shortcode) share the same values.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JG_Slot_Keys {

    private static $k = null;

    private static function r() {
        return 'jg' . substr(md5(mt_rand()), 0, 8);
    }

    /**
     * Return the full set of random keys for this request.
     */
    public static function get() {
        if (self::$k === null) {
            self::$k = array(
                // Element IDs (9-char suffix for extra uniqueness)
                'id_cid'    => 'jg' . substr(md5(mt_rand()), 0, 9),
                'id_lid'    => 'jg' . substr(md5(mt_rand()), 0, 9),
                'id_iid'    => 'jg' . substr(md5(mt_rand()), 0, 9),
                'id_spin'   => 'jg' . substr(md5(mt_rand()), 0, 9),
                'id_tag'    => 'jg' . substr(md5(mt_rand()), 0, 9),
                // CSS class names
                'cls_wrap'   => self::r(),
                'cls_box'    => self::r(),
                'cls_tag'    => self::r(),
                'cls_fs'     => self::r(),
                'cls_fs_in'  => self::r(),
                'cls_fs_tag' => self::r(),
                'cls_anim'   => self::r(),
            );
        }
        return self::$k;
    }

    /**
     * Build the complete CSS string for all slot elements using random class names.
     * This is injected as an inline style so the static CSS file contains no
     * predictable selectors that ad blockers could target.
     */
    public static function get_css() {
        $k  = self::get();
        $w  = $k['cls_wrap'];
        $b  = $k['cls_box'];
        $t  = $k['cls_tag'];
        $fs = $k['cls_fs'];
        $fi = $k['cls_fs_in'];
        $ft = $k['cls_fs_tag'];
        $a  = $k['cls_anim'];

        return "
.{$w}{text-align:center;margin:16px auto}
.{$t}{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;font-size:calc(10 * var(--jg));font-weight:600;color:#8d2324;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;user-select:none}
.{$b}{max-width:100%;box-sizing:border-box}
@media(max-width:768px){
  .{$w}{margin:4px auto}
  .{$t}{font-size:calc(8 * var(--jg));margin-bottom:2px;letter-spacing:.8px}
  .{$b}{max-width:100%!important;width:100%!important;padding:0 10px;margin:0 auto!important;box-sizing:border-box}
}
.{$fs}{position:absolute;bottom:20px;left:50%;transform:translateX(-50%);z-index:1000;display:flex;flex-direction:column;align-items:center;pointer-events:auto;max-width:calc(100% - 160px);animation:{$a} .35s ease-out}
@keyframes {$a}{from{opacity:0;transform:translateX(-50%) translateY(10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
.{$ft}{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;font-size:calc(9 * var(--jg));font-weight:600;color:rgba(255,255,255,.8);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:4px;text-shadow:0 1px 4px rgba(0,0,0,.5);user-select:none;pointer-events:none}
.{$fi}{background:rgba(255,255,255,.95);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.18);overflow:hidden;max-width:728px;transition:opacity .3s ease}
.{$fi} a{display:block;line-height:0;text-decoration:none}
.{$fi} img{display:block;width:100%;height:auto;object-fit:contain}
@media(min-width:769px){body.jg-fullscreen-active .{$fs}{left:calc((100% - min(380px,30vw)) / 2);max-width:calc(100% - min(380px,30vw) - 200px)}}
@media(min-width:769px){body.jg-desktop-wide-active .{$fs}{bottom:auto;top:12px;left:calc((100% - min(380px,30vw)) / 2);max-width:calc(100% - min(380px,30vw) - 200px);transform:translateX(-50%)}}
@media(max-width:768px){
  body.jg-fullscreen-active .{$fs}{bottom:80px;left:50%;max-width:calc(100% - 24px);transform:translateX(-50%) scale(1.8);transform-origin:center bottom}
  .{$fi}{max-width:100%;border-radius:6px}
  .{$ft}{font-size:calc(8 * var(--jg));letter-spacing:1px;margin-bottom:2px}
}
@media(max-width:380px){body.jg-fullscreen-active .{$fs}{bottom:76px;max-width:calc(100% - 16px);transform:translateX(-50%) scale(1.7);transform-origin:center bottom}}
";
    }
}
