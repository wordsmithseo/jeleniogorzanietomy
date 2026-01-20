/**
 * JG Banner Manager - Fair rotation with session storage
 */
(function($) {
  'use strict';

  var JG_Banner = {
    config: null,
    banners: [],
    currentIndex: 0,
    currentBanner: null,
    sessionKey: 'jg_banner_rotation_index',

    /**
     * Initialize banner system
     */
    init: function() {
      this.config = window.JG_BANNER_CFG || {};

      if ($('#jg-banner-container').length) {
        this.loadBanners();
      }
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
     * Hide banner if none available
     */
    hideBanner: function() {
      $('#jg-banner-container').hide();
    }
  };

  // Initialize on DOM ready
  $(document).ready(function() {
    JG_Banner.init();
  });

})(jQuery);
