(function($) {
  'use strict';

  var JG_Ext = {
    config: null,
    banners: [],
    currentIndex: 0,
    currentBanner: null,
    sessionKey: 'jg_ext_idx',
    ids: null,

    init: function() {
      this.config = window.JG_EXT_CFG || {};
      var $wrap = $('[data-cid]').first();
      if (!$wrap.length) return;

      // Read server-generated random IDs from data attributes
      this.ids = {
        container: $wrap.data('cid'),
        link:      $wrap.data('lid'),
        image:     $wrap.data('iid'),
        loading:   $wrap.data('spin'),
        label:     $wrap.data('tag')
      };

      this.loadContent();
    },

    loadContent: function() {
      var self = this;
      $.ajax({
        url:  self.config.ajax,
        type: 'POST',
        data: { action: self.config.act.fetch },
        success: function(response) {
          if (response.success && response.data.banners && response.data.banners.length > 0) {
            self.banners = response.data.banners;
            self.initRotation();
          } else {
            self.hideSlot();
          }
        },
        error: function() {
          self.hideSlot();
        }
      });
    },

    initRotation: function() {
      var stored = sessionStorage.getItem(this.sessionKey);
      this.currentIndex = (stored !== null) ? parseInt(stored, 10) : 0;
      if (this.currentIndex >= this.banners.length) this.currentIndex = 0;
      this.displayItem();
      this.currentIndex = (this.currentIndex + 1) % this.banners.length;
      sessionStorage.setItem(this.sessionKey, this.currentIndex);
    },

    displayItem: function() {
      var item = this.banners[this.currentIndex];
      this.currentBanner = item;

      var $container = $('#' + this.ids.container);
      var $loading   = $('#' + this.ids.loading);
      var $link      = $('#' + this.ids.link);
      var $image     = $('#' + this.ids.image);

      $container.attr('data-bid', item.id);
      $image.attr('src', item.image_url);
      $image.attr('alt', '');
      $link.attr('href', item.link_url);

      $image.on('load', function() {
        $loading.hide();
        $link.show();
      });

      this.recordView(item.id);

      $link.on('click', function() {
        this.recordAction(item.id);
      }.bind(this));
    },

    recordView: function(itemId) {
      $.ajax({
        url:  this.config.ajax,
        type: 'POST',
        data: { action: this.config.act.view, banner_id: itemId }
      });
    },

    recordAction: function(itemId) {
      if (navigator.sendBeacon) {
        var fd = new FormData();
        fd.append('action', this.config.act.engage);
        fd.append('banner_id', itemId);
        navigator.sendBeacon(this.config.ajax, fd);
      } else {
        $.ajax({
          url:   this.config.ajax,
          type:  'POST',
          async: false,
          data:  { action: this.config.act.engage, banner_id: itemId }
        });
      }
    },

    hideSlot: function() {
      $('#' + this.ids.loading).hide();
      $('#' + this.ids.label).hide();
      var cta = '<a href="/reklama" style="display:flex !important;align-items:center;justify-content:center;gap:16px;width:100%;min-height:90px;padding:16px 28px;box-sizing:border-box;border-radius:4px;text-decoration:none;background:#8d2324;font-family:system-ui,-apple-system,sans-serif">' +
        '<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,0.7);flex-shrink:0">Reklama</span>' +
        '<span style="font-size:16px;font-weight:700;color:#fff">Tu może być Twoja reklama — napisz do nas</span>' +
        '<span style="font-size:20px;flex-shrink:0;color:#fff">→</span>' +
      '</a>';
      $('#' + this.ids.container).css({ display: 'block', minHeight: '90px', overflow: 'visible' }).html(cta);
    }
  };

  $(document).ready(function() {
    JG_Ext.init();
  });

})(jQuery);
