/**
 * JG Banner Manager - Fair rotation with session storage and anti-adblock obfuscation
 */
(function($) {
  'use strict';

  var JG_Banner = {
    config: null,
    banners: [],
    currentIndex: 0,
    currentBanner: null,
    sessionKey: 'jg_banner_rotation_index',
    obfuscationInterval: null,

    /**
     * Initialize banner system
     */
    init: function() {
      this.config = window.JG_BANNER_CFG || {};

      if ($('#jg-banner-container').length) {
        this.applyObfuscation();
        this.loadBanners();
        this.startObfuscationRefresh();
      }
    },

    /**
     * Generate random class name (anti-adblock)
     */
    randomClassName: function(length) {
      length = length || 8;
      var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
      var result = '';
      for (var i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      return 'obf-' + result;
    },

    /**
     * Apply obfuscation classes (anti-adblock)
     */
    applyObfuscation: function() {
      var self = this;
      // Apply to both original and fullscreen banner containers
      var containers = [$('#jg-banner-container'), $('.jg-fs-promo-inner')];
      containers.forEach(function($container) {
        if (!$container.length) return;

        // Remove old obfuscation classes
        var classes = $container.attr('class');
        if (classes) {
          var classList = classes.split(/\s+/);
          classList.forEach(function(cls) {
            if (cls.startsWith('obf-')) {
              $container.removeClass(cls);
            }
          });
        }

        // Add new random class
        var newClass = self.randomClassName();
        $container.addClass(newClass);

        // Refresh image timestamp to bypass cache
        $container.find('img').each(function() {
          var $img = $(this);
          if ($img.attr('src')) {
            var currentSrc = $img.attr('src').split('?')[0];
            $img.attr('src', currentSrc + '?t=' + Date.now());
          }
        });
      });
    },

    /**
     * Start periodic obfuscation refresh (every 15 minutes)
     */
    startObfuscationRefresh: function() {
      var self = this;
      // Refresh obfuscation every 15 minutes (900000ms)
      this.obfuscationInterval = setInterval(function() {
        self.applyObfuscation();
      }, 900000);
    },

    /**
     * Load active banners from server
     */
    loadBanners: function() {
      var self = this;

      $.ajax({
        url: self.config.ajax,
        type: 'POST',
        data: {
          action: 'jg_get_banner'
        },
        success: function(response) {
          if (response.success && response.data.banners && response.data.banners.length > 0) {
            self.banners = response.data.banners;
            self.initRotation();
          } else {
            self.hideBanner();
          }
        },
        error: function() {
          self.hideBanner();
        }
      });
    },

    /**
     * Initialize rotation with session storage
     */
    initRotation: function() {
      // Get last index from session storage
      var storedIndex = sessionStorage.getItem(this.sessionKey);

      if (storedIndex !== null) {
        this.currentIndex = parseInt(storedIndex, 10);
      } else {
        this.currentIndex = 0;
      }

      // If index is out of bounds, reset to 0
      if (this.currentIndex >= this.banners.length) {
        this.currentIndex = 0;
      }

      // Display current banner
      this.displayBanner();

      // Increment index for next page view (cyclic rotation)
      this.currentIndex = (this.currentIndex + 1) % this.banners.length;
      sessionStorage.setItem(this.sessionKey, this.currentIndex);
    },

    /**
     * Display banner
     */
    displayBanner: function() {
      var banner = this.banners[this.currentIndex];
      this.currentBanner = banner;

      var $container = $('#jg-banner-container');
      var $loading = $('#jg-banner-loading');
      var $link = $('#jg-banner-link');
      var $image = $('#jg-banner-image');

      // Set banner properties
      $container.attr('data-bid', banner.id);
      $image.attr('src', banner.image_url);
      $image.attr('alt', banner.title);
      $link.attr('href', banner.link_url);

      // Show banner when image loads
      $image.on('load', function() {
        $loading.hide();
        $link.show();
      });

      // Track impression after banner is visible
      this.trackImpression(banner.id);

      // Track click
      $link.on('click', function(e) {
        // Let the link open naturally, but track click
        this.trackClick(banner.id);
      }.bind(this));
    },

    /**
     * Track banner impression
     */
    trackImpression: function(bannerId) {
      $.ajax({
        url: this.config.ajax,
        type: 'POST',
        data: {
          action: 'jg_banner_impression',
          banner_id: bannerId
        }
        // Silent tracking - no need to handle response
      });
    },

    /**
     * Track banner click
     */
    trackClick: function(bannerId) {
      // Use sendBeacon for reliable tracking even if page unloads
      if (navigator.sendBeacon) {
        var formData = new FormData();
        formData.append('action', 'jg_banner_click');
        formData.append('banner_id', bannerId);
        navigator.sendBeacon(this.config.ajax, formData);
      } else {
        // Fallback to synchronous AJAX
        $.ajax({
          url: this.config.ajax,
          type: 'POST',
          async: false,
          data: {
            action: 'jg_banner_click',
            banner_id: bannerId
          }
        });
      }
    },

    /**
     * Hide banner if none available - show advertise CTA instead
     */
    hideBanner: function() {
      $('#jg-banner-loading').hide();
      $('#jg-banner-label').hide();
      var $fallback = $('#jg-banner-fallback-cta');
      if ($fallback.length) {
        $fallback.css('display', 'flex');
      } else {
        $('#jg-banner-container').html(
          '<a href="/reklama" style="display:flex;align-items:center;justify-content:center;gap:12px;width:100%;min-height:90px;padding:12px 24px;box-sizing:border-box;border:1.5px dashed #d1d5db;border-radius:4px;text-decoration:none;color:#6b7280;font-size:14px;background:repeating-linear-gradient(-45deg,#fafafa,#fafafa 10px,#f3f4f6 10px,#f3f4f6 20px)">' +
            '<span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;flex-shrink:0">Reklama</span>' +
            '<span style="font-weight:500">Tu może być Twoja reklama — napisz do nas</span>' +
            '<span style="font-size:16px;flex-shrink:0">→</span>' +
          '</a>'
        );
      }
    }
  };

  // Initialize on DOM ready
  $(document).ready(function() {
    JG_Banner.init();
  });

})(jQuery);
