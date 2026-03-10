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
      var ctaHtml = '<a href="/reklama" style="display:flex !important;align-items:center;justify-content:center;gap:12px;width:100%;height:90px;max-height:90px;padding:6px 16px;box-sizing:border-box;overflow:hidden;border-radius:4px;text-decoration:none;background:#8d2324;font-family:system-ui,-apple-system,sans-serif">' +
        '<span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,0.7);flex-shrink:0">Reklama</span>' +
        '<span style="font-size:13px;font-weight:700;color:#fff">Tu może być Twoja reklama — napisz do nas</span>' +
        '<span style="font-size:14px;flex-shrink:0;color:#fff">→</span>' +
      '</a>';
      var $container = $('#jg-banner-container');
      $container.css({ display: 'block', height: '90px', maxHeight: '90px', overflow: 'hidden' }).html(ctaHtml);
    }
  };

  // Initialize on DOM ready
  $(document).ready(function() {
    JG_Banner.init();
  });

})(jQuery);
