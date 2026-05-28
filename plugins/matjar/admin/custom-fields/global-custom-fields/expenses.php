<?php

class Global_Expenses_Field
{

    /**
     * Option key.
     */
    const OPTION_KEY = 'global_expenses_amount';

    public function __construct()
    {

        add_action(
            'admin_menu',
            array($this, 'register_menu')
        );

        add_action(
            'admin_init',
            array($this, 'register_setting')
        );
    }

    /**
     * Register admin menu page.
     */
    public function register_menu()
    {

        add_options_page(
            'Expenses Settings',
            'Expenses',
            'manage_options',
            'expenses-settings',
            array($this, 'render_page')
        );
    }

    /**
     * Register setting.
     */
    public function register_setting()
    {

        register_setting(
            'expenses_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'number',
                'sanitize_callback' => 'floatval',
                'default'           => 0,
            )
        );
    }

    /**
     * Render settings page.
     */
    public function render_page()
    {

?>
        <div class="wrap">
            <h1>Expenses Settings</h1>

            <form method="post" action="options.php">

                <?php
                settings_fields('expenses_settings_group');
                ?>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            Global Expenses
                        </th>

                        <td>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>"
                                value="<?php echo esc_attr(get_option(self::OPTION_KEY, 0)); ?>"
                                class="regular-text">
                        </td>
                    </tr>

                </table>

                <?php submit_button(); ?>

            </form>
        </div>
<?php
    }
}

new Global_Expenses_Field();
