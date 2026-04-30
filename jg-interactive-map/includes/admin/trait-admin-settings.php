<?php
/**
 * Trait: Settings admin page
 *
 * @package JG_Interactive_Map
 */

trait JG_Map_Admin_Settings {

    public function render_settings_page() {
        // Handle form submission
        if (isset($_POST['jg_map_save_settings']) && check_admin_referer('jg_map_settings_nonce')) {
            $onboarding_enabled = isset($_POST['jg_map_onboarding_enabled']) ? 1 : 0;
            update_option('jg_map_onboarding_enabled', $onboarding_enabled);

            $registration_enabled = isset($_POST['jg_map_registration_enabled']) ? 1 : 0;
            $registration_disabled_message = sanitize_textarea_field($_POST['jg_map_registration_disabled_message'] ?? '');

            update_option('jg_map_registration_enabled', $registration_enabled);
            update_option('jg_map_registration_disabled_message', $registration_disabled_message);

            // Terms of service settings
            $terms_type = sanitize_text_field($_POST['jg_map_terms_type'] ?? 'url');
            if ($terms_type === 'url') {
                update_option('jg_map_terms_url', esc_url_raw($_POST['jg_map_terms_url'] ?? ''));
                update_option('jg_map_terms_content', '');
            } else {
                update_option('jg_map_terms_url', '');
                update_option('jg_map_terms_content', wp_kses_post($_POST['jg_map_terms_content'] ?? ''));
            }

            // Privacy policy settings
            $privacy_type = sanitize_text_field($_POST['jg_map_privacy_type'] ?? 'url');
            if ($privacy_type === 'url') {
                update_option('jg_map_privacy_url', esc_url_raw($_POST['jg_map_privacy_url'] ?? ''));
                update_option('jg_map_privacy_content', '');
            } else {
                update_option('jg_map_privacy_url', '');
                update_option('jg_map_privacy_content', wp_kses_post($_POST['jg_map_privacy_content'] ?? ''));
            }

            // Social OAuth credentials
            update_option('jg_map_google_client_id', sanitize_text_field($_POST['jg_map_google_client_id'] ?? ''));
            update_option('jg_map_google_client_secret', sanitize_text_field($_POST['jg_map_google_client_secret'] ?? ''));
            update_option('jg_map_facebook_app_id', sanitize_text_field($_POST['jg_map_facebook_app_id'] ?? ''));
            update_option('jg_map_facebook_app_secret', sanitize_text_field($_POST['jg_map_facebook_app_secret'] ?? ''));

            // IndexNow: regenerate key if requested
            if (!empty($_POST['jg_map_indexnow_regenerate'])) {
                update_option('jg_map_indexnow_key', wp_generate_uuid4());
            }

            echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
        }

        // Auto-generate IndexNow key on first use (no manual setup needed)
        $indexnow_key = get_option('jg_map_indexnow_key', '');
        if ($indexnow_key === '') {
            $indexnow_key = wp_generate_uuid4();
            update_option('jg_map_indexnow_key', $indexnow_key);
        }

        $onboarding_enabled = get_option('jg_map_onboarding_enabled', 1); // Enabled by default
        $registration_enabled = get_option('jg_map_registration_enabled', 1); // Enabled by default
        $registration_disabled_message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');
        $terms_url = get_option('jg_map_terms_url', '');
        $terms_content = get_option('jg_map_terms_content', '');
        $terms_type = $terms_url ? 'url' : ($terms_content ? 'content' : 'url');
        $privacy_url = get_option('jg_map_privacy_url', '');
        $privacy_content = get_option('jg_map_privacy_content', '');
        $privacy_type = $privacy_url ? 'url' : ($privacy_content ? 'content' : 'url');
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Ustawienia JG Map'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('jg_map_settings_nonce'); ?>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Onboarding i samouczek</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="jg_map_onboarding_enabled">Samouczek</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="jg_map_onboarding_enabled"
                                           id="jg_map_onboarding_enabled"
                                           value="1"
                                           <?php checked($onboarding_enabled, 1); ?>>
                                    <strong>Włącz onboarding dla użytkowników</strong>
                                </label>
                                <p class="description">
                                    Gdy włączone: nowym użytkownikom wyświetla się modal powitalny, wskazówki kontekstowe (tipy) oraz tooltopy na elementach UI.
                                    Gdy wyłączone: żadna z warstw samouczka nie jest inicjalizowana — przycisk pomocy (?) i panel pomocy również nie pojawią się na mapie.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Rejestracja użytkowników</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="jg_map_registration_enabled">Rejestracja</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="jg_map_registration_enabled"
                                           id="jg_map_registration_enabled"
                                           value="1"
                                           <?php checked($registration_enabled, 1); ?>>
                                    <strong>Włącz rejestrację nowych użytkowników</strong>
                                </label>
                                <p class="description">
                                    Gdy wyłączone, zakładka rejestracji w modalu pokaże komunikat zamiast formularza.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="jg_map_registration_disabled_message">Komunikat gdy wyłączona</label>
                            </th>
                            <td>
                                <textarea name="jg_map_registration_disabled_message"
                                          id="jg_map_registration_disabled_message"
                                          rows="3"
                                          class="large-text"
                                          placeholder="Rejestracja jest obecnie wyłączona. Spróbuj ponownie później."><?php echo esc_textarea($registration_disabled_message); ?></textarea>
                                <p class="description">
                                    Ten komunikat zostanie wyświetlony użytkownikom gdy rejestracja jest wyłączona.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Regulamin serwisu</h2>
                    <p class="description" style="margin-bottom:16px">Dokument regulaminu wyświetlany w formularzu rejestracji. Możesz podać URL istniejącej podstrony WordPress lub wpisać treść bezpośrednio.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Źródło regulaminu</th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:8px">
                                        <input type="radio" name="jg_map_terms_type" value="url" <?php checked($terms_type, 'url'); ?> class="jg-terms-type-radio">
                                        <strong>URL podstrony WordPress</strong>
                                    </label>
                                    <label style="display:block">
                                        <input type="radio" name="jg_map_terms_type" value="content" <?php checked($terms_type, 'content'); ?> class="jg-terms-type-radio">
                                        <strong>Wpisz treść</strong>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr class="jg-terms-url-row" <?php echo $terms_type === 'content' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_terms_url">URL regulaminu</label>
                            </th>
                            <td>
                                <div style="position:relative">
                                    <input type="text"
                                           name="jg_map_terms_url"
                                           id="jg_map_terms_url"
                                           value="<?php echo esc_attr($terms_url); ?>"
                                           class="large-text jg-page-autocomplete"
                                           placeholder="Zacznij pisać nazwę strony..."
                                           autocomplete="off">
                                    <div id="jg_map_terms_url_suggestions" class="jg-autocomplete-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 4px 4px;max-height:200px;overflow:auto;z-index:100;box-shadow:0 4px 6px rgba(0,0,0,0.1)"></div>
                                </div>
                                <p class="description">Podaj URL lub zacznij wpisywać nazwę strony WordPress, aby zobaczyć podpowiedzi.</p>
                            </td>
                        </tr>
                        <tr class="jg-terms-content-row" <?php echo $terms_type === 'url' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_terms_content">Treść regulaminu</label>
                            </th>
                            <td>
                                <textarea name="jg_map_terms_content"
                                          id="jg_map_terms_content"
                                          rows="10"
                                          class="large-text"
                                          placeholder="Wpisz treść regulaminu..."><?php echo esc_textarea($terms_content); ?></textarea>
                                <p class="description">Treść regulaminu zostanie wyświetlona użytkownikom w okienku modalnym. Dozwolony HTML.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Polityka prywatności</h2>
                    <p class="description" style="margin-bottom:16px">Dokument polityki prywatności wyświetlany w formularzu rejestracji. Możesz podać URL istniejącej podstrony WordPress lub wpisać treść bezpośrednio.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Źródło polityki prywatności</th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:8px">
                                        <input type="radio" name="jg_map_privacy_type" value="url" <?php checked($privacy_type, 'url'); ?> class="jg-privacy-type-radio">
                                        <strong>URL podstrony WordPress</strong>
                                    </label>
                                    <label style="display:block">
                                        <input type="radio" name="jg_map_privacy_type" value="content" <?php checked($privacy_type, 'content'); ?> class="jg-privacy-type-radio">
                                        <strong>Wpisz treść</strong>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr class="jg-privacy-url-row" <?php echo $privacy_type === 'content' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_privacy_url">URL polityki prywatności</label>
                            </th>
                            <td>
                                <div style="position:relative">
                                    <input type="text"
                                           name="jg_map_privacy_url"
                                           id="jg_map_privacy_url"
                                           value="<?php echo esc_attr($privacy_url); ?>"
                                           class="large-text jg-page-autocomplete"
                                           placeholder="Zacznij pisać nazwę strony..."
                                           autocomplete="off">
                                    <div id="jg_map_privacy_url_suggestions" class="jg-autocomplete-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 4px 4px;max-height:200px;overflow:auto;z-index:100;box-shadow:0 4px 6px rgba(0,0,0,0.1)"></div>
                                </div>
                                <p class="description">Podaj URL lub zacznij wpisywać nazwę strony WordPress, aby zobaczyć podpowiedzi.</p>
                            </td>
                        </tr>
                        <tr class="jg-privacy-content-row" <?php echo $privacy_type === 'url' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_privacy_content">Treść polityki prywatności</label>
                            </th>
                            <td>
                                <textarea name="jg_map_privacy_content"
                                          id="jg_map_privacy_content"
                                          rows="10"
                                          class="large-text"
                                          placeholder="Wpisz treść polityki prywatności..."><?php echo esc_textarea($privacy_content); ?></textarea>
                                <p class="description">Treść polityki prywatności zostanie wyświetlona użytkownikom w okienku modalnym. Dozwolony HTML.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Logowanie przez media społecznościowe (OAuth)</h2>
                    <p class="description" style="margin-bottom:16px">
                        Aby umożliwić logowanie przez Google i Facebook, utwórz aplikacje OAuth w odpowiednich konsolach
                        deweloperskich i wpisz poniżej dane uwierzytelniające. Pola zostaw puste, aby wyłączyć dany provider.
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="jg_map_google_client_id">Google Client ID</label></th>
                            <td>
                                <input type="text" name="jg_map_google_client_id" id="jg_map_google_client_id"
                                       value="<?php echo esc_attr(get_option('jg_map_google_client_id', '')); ?>"
                                       class="regular-text" placeholder="xxxxxxxxxxxx-xxxxxxxx.apps.googleusercontent.com">
                                <p class="description">Redirect URI do ustawienia w Google Cloud Console:<br>
                                    <code><?php echo esc_html(admin_url('admin-ajax.php') . '?action=jg_google_oauth_callback'); ?></code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="jg_map_google_client_secret">Google Client Secret</label></th>
                            <td>
                                <input type="password" name="jg_map_google_client_secret" id="jg_map_google_client_secret"
                                       value="<?php echo esc_attr(get_option('jg_map_google_client_secret', '')); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="jg_map_facebook_app_id">Facebook App ID</label></th>
                            <td>
                                <input type="text" name="jg_map_facebook_app_id" id="jg_map_facebook_app_id"
                                       value="<?php echo esc_attr(get_option('jg_map_facebook_app_id', '')); ?>"
                                       class="regular-text" placeholder="123456789012345">
                                <p class="description">Redirect URI do ustawienia w Facebook Developer Console:<br>
                                    <code><?php echo esc_html(admin_url('admin-ajax.php') . '?action=jg_facebook_oauth_callback'); ?></code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="jg_map_facebook_app_secret">Facebook App Secret</label></th>
                            <td>
                                <input type="password" name="jg_map_facebook_app_secret" id="jg_map_facebook_app_secret"
                                       value="<?php echo esc_attr(get_option('jg_map_facebook_app_secret', '')); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>IndexNow – automatyczne powiadamianie wyszukiwarek</h2>
                    <p class="description" style="margin-bottom:16px">
                        IndexNow to protokół obsługiwany przez Bing i Yandex. Plugin automatycznie pinguje wyszukiwarki
                        za każdym razem gdy zatwierdzisz nowe miejsce lub edycję. Klucz poniżej jest generowany
                        automatycznie i hostowany przez plugin pod adresem
                        <code><?php echo esc_html(home_url('/' . $indexnow_key . '.txt')); ?></code> –
                        <strong>nie musisz nic konfigurować ani rejestrować się nigdzie</strong>.
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Klucz IndexNow</th>
                            <td>
                                <code style="font-size:14px;background:#f6f7f7;padding:6px 10px;border-radius:4px;display:inline-block;letter-spacing:.5px"><?php echo esc_html($indexnow_key); ?></code>
                                <p class="description" style="margin-top:8px">
                                    Plik weryfikacyjny dostępny pod:
                                    <a href="<?php echo esc_url(home_url('/' . $indexnow_key . '.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/' . $indexnow_key . '.txt')); ?></a>
                                </p>
                                <label style="display:block;margin-top:12px">
                                    <input type="checkbox" name="jg_map_indexnow_regenerate" value="1">
                                    Wygeneruj nowy klucz (użyj tylko gdy coś nie działa)
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit"
                           name="jg_map_save_settings"
                           id="submit"
                           class="button button-primary"
                           value="Zapisz ustawienia">
                </p>
            </form>
        </div>

        <script>
        jQuery(function($) {
            // Toggle terms URL/content fields based on radio selection
            $('.jg-terms-type-radio').on('change', function() {
                if ($(this).val() === 'url') {
                    $('.jg-terms-url-row').show();
                    $('.jg-terms-content-row').hide();
                } else {
                    $('.jg-terms-url-row').hide();
                    $('.jg-terms-content-row').show();
                }
            });

            // Toggle privacy URL/content fields based on radio selection
            $('.jg-privacy-type-radio').on('change', function() {
                if ($(this).val() === 'url') {
                    $('.jg-privacy-url-row').show();
                    $('.jg-privacy-content-row').hide();
                } else {
                    $('.jg-privacy-url-row').hide();
                    $('.jg-privacy-content-row').show();
                }
            });

            // Page URL autocomplete for both terms and privacy URL fields
            var autocompleteTimer = null;
            $('.jg-page-autocomplete').on('input', function() {
                var input = $(this);
                var suggestionsDiv = input.parent().find('.jg-autocomplete-suggestions');
                var query = input.val().trim();

                clearTimeout(autocompleteTimer);

                if (query.length < 2) {
                    suggestionsDiv.hide().empty();
                    return;
                }

                // If it looks like a URL already, don't search
                if (query.indexOf('http') === 0 || query.indexOf('/') === 0) {
                    suggestionsDiv.hide().empty();
                    return;
                }

                autocompleteTimer = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'jg_map_search_pages',
                            search: query,
                            _wpnonce: '<?php echo wp_create_nonce('jg_map_search_pages'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data && response.data.length > 0) {
                                var html = '';
                                $.each(response.data, function(i, page) {
                                    html += '<div class="jg-autocomplete-item" data-url="' + page.url + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:calc(13 * var(--jg))">' +
                                        '<strong>' + page.title + '</strong>' +
                                        '<div style="color:#999;font-size:calc(11 * var(--jg));margin-top:2px">' + page.url + '</div>' +
                                    '</div>';
                                });
                                suggestionsDiv.html(html).show();

                                suggestionsDiv.find('.jg-autocomplete-item').on('mouseenter', function() {
                                    $(this).css('background', '#f0f0f0');
                                }).on('mouseleave', function() {
                                    $(this).css('background', '#fff');
                                }).on('click', function() {
                                    input.val($(this).data('url'));
                                    suggestionsDiv.hide().empty();
                                });
                            } else {
                                suggestionsDiv.hide().empty();
                            }
                        }
                    });
                }, 300);
            });

            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.jg-page-autocomplete, .jg-autocomplete-suggestions').length) {
                    $('.jg-autocomplete-suggestions').hide().empty();
                }
            });

        });
        </script>
        <?php
    }
}
