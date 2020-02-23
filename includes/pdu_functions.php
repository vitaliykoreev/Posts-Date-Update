<?php
	// Добавление ссылки в меню
	function pdu_add_menu_link()
	{
		add_menu_page(
			'Posts Date Update Settings',
			'PDU Settings',
			'manage_options',
			'pdu_admin_settings',
			'pdu_admin_settings'
		);
	}
	add_action('admin_menu', 'pdu_add_menu_link');

	// Добавление настроек плагина
	function pdu_add_settings() {
		// Добавление секции с опциями
		add_settings_section( 'pdu_settings_section', 'Основные настройки', '', 'pdu_settings');

		// Статус задачи
		add_settings_field('pdu_field_status', 'Статус', 'pdu_display_field_status', 'pdu_settings', 'pdu_settings_section');

		// Выбор категорий
		add_settings_field('pdu_field_categories', 'Выбор категории постов для обновления', 'pdu_display_field_categories', 'pdu_settings', 'pdu_settings_section');

		// Частота обновления
		add_settings_field('pdu_field_frequency', 'Частота обновления', 'pdu_display_field_frequency', 'pdu_settings', 'pdu_settings_section');

		// Увеличить на
		add_settings_field('pdu_field_increase_value', 'Увеличить дату публикации на ', 'pdu_display_field_increase_value', 'pdu_settings', 'pdu_settings_section');
		//add_settings_field('pdu_field_increase_unit', 'Увеличить дату публикации на ', 'pdu_display_field_increase_value', 'pdu_settings', 'pdu_settings_section');

		register_setting('pdu_settings_group', 'pdu_field_categories', 'sanitize_callback');
		register_setting('pdu_settings_group', 'pdu_field_frequency', 'sanitize_callback');
		register_setting('pdu_settings_group', 'pdu_field_increase_value', 'sanitize_increase_value_callback');
		register_setting('pdu_settings_group', 'pdu_field_increase_unit', 'sanitize_callback');
		register_setting('pdu_settings_group', 'pdu_field_status', 'sanitize_callback');
	}
	add_action('admin_init', 'pdu_add_settings');

	// Добавление тестового интервала
    add_filter('cron_schedules', 'cron_new_schedule');
    function cron_new_schedule($schedules) {
        $schedules['oneminute'] = array(
            'interval' => 60 * 1,
            'display' => 'Раз в 1 минуту'
        );
        return $schedules;
    }

	// Отображение поля со статусом
	function pdu_display_field_status() {
		$optionValue = get_option('pdu_field_status', 'off');

		$values = [
			"on" => "Включено",
			"off" => "Выключено"
		];

		foreach($values as $key => $value) {
			echo "<input type='radio' id='pdu_setting_status_".$key."' name='pdu_field_status' ".($optionValue == $key ? "checked" : "")." value='".$key."'> <label for='pdu_setting_status_".$key."'>".$value."</label><br>";
		}
	}

	// Отображение поля с категориями
	function pdu_display_field_categories() {
		$value = get_option('pdu_field_categories', '');

		$categories = get_categories([
			"hide_empty" => 0,
		]);

		echo "<select id='pdu_setting_categories' name='pdu_field_categories[]' multiple>";
		foreach ($categories as $category) {
			echo "<option value='".$category->cat_ID."' ".(in_array($category->cat_ID, $value) ? "selected" : "").">".$category->cat_name."</option>";
		}
		echo "</select>";
	}

	// Отображение поля с частотой обновления
	function pdu_display_field_frequency() {
		$optionValue = get_option('pdu_field_frequency', 'hourly');
		$allowedScheduleValues = wp_get_schedules();

		echo "<select id='pdu_setting_frequency' name='pdu_field_frequency'>";
		foreach ($allowedScheduleValues as $key => $value) {
			echo "<option value='".$key."' ".($optionValue == $key ? "selected" : "").">".$value["display"]."</option>";
		}
		echo "</select>";
	}

	// Отображение поля с количеством часов/дней для инкременирования даты
	function pdu_display_field_increase_value() {
		$optionValue = get_option('pdu_field_increase_value', '1');
		$optionValueUnit = get_option('pdu_field_increase_unit', 'day');

		$allowedValues = [
			"hour" => "Часов",
			"day" => "Дней"
		];

		echo "<input type='number' name='pdu_field_increase_value' value='".$optionValue."'>";

		echo "<select id='pdu_setting_increase_unit' name='pdu_field_increase_unit' style='transform: translateY(-2px);'>";
		foreach ($allowedValues as $key => $value) {
			echo "<option value='".$key."' ".($optionValueUnit == $key ? "selected" : "").">".$value."</option>";
		}
		echo "</section>";
	}

	// Отображение страницы с настройками
	function pdu_admin_settings() {
   		?>
		<div class="wrap">
			<?php
				settings_errors();
			?>

			<h2><?php echo get_admin_page_title() ?></h2>

			<form action="options.php" method="post">
				<?php
					settings_fields("pdu_settings_group");     // скрытые защитные поля
					do_settings_sections("pdu_settings"); // секции с настройками (опциями).
					submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// Действие на изменение опции запуска
	add_action('update_option_pdu_field_status', function($old_value, $value) {
        if ($value == "on") {
            $optionFrequency = get_option('pdu_field_frequency');

	        if(!wp_next_scheduled('pdu_update_event')) {
		        wp_schedule_event(time(), $optionFrequency, 'pdu_update_event');
	        }
        } else {
	        wp_clear_scheduled_hook('pdu_update_event');
        }
	}, 10, 2);

    // Действие на изменение опции частоты
    add_action('update_option_pdu_field_frequency', function($old_value, $value) {
	    $optionStatus = get_option('pdu_field_status');

        if ($optionStatus == "on") {
            if(wp_next_scheduled('pdu_update_event')) {
	            wp_clear_scheduled_hook('pdu_update_event');
            }

	        wp_schedule_event(time(), $value, 'pdu_update_event');
        } else {
            wp_clear_scheduled_hook('pdu_update_event');
        }
    }, 10, 2);

	// Функция при активации плагина
	function pdu_activation() {
		wp_clear_scheduled_hook('pdu_update_event');
	}

	// Функция при деактивации плагина
	function pdu_deactivation() {
		wp_clear_scheduled_hook('pdu_update_event');
	}

	add_action('pdu_update_event', 'update');

	// Обновить посты
	function update() {
		$categories = get_option('pdu_field_categories');
		$optionIncreaseValue = get_option('pdu_field_increase_value');
		$optionIncreaseUnit = get_option('pdu_field_increase_unit');

		$increaseString = "+".$optionIncreaseValue." ".$optionIncreaseUnit;

		if (!empty($categories)) {
		    $posts = get_posts([
		        "posts_per_page" => -1,
                "cat" => $categories,
            ]);

		    foreach ($posts as $post) {
		        $date = $post->post_date;
		        $newDate = date("Y-m-d H:i:s", strtotime($increaseString, strtotime($date)));

			    wp_update_post([
				    "ID" => $post->ID,
				    "post_date" => $newDate,
				    "post_date_gmt" => get_gmt_from_date($newDate)
			    ]);
            }
        }
	}