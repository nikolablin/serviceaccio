<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\Moysklad;
use app\models\OrdersConfigTable;
use app\models\CashRegister;

class OrdersConfig extends Model
{
    /**
     * ВАЖНО:
     * - Для "юрика" (один project, несколько action_type) рендерим ОДНУ форму,
     *   а поля делаем массивами: configs[action_type][field]
     * - Для остальных проектов — обычная форма на один action_type=0.
     */
    public static function getOrderConfigForm($mid, $references)
    {
        $moysklad = new Moysklad();

        // Общие справочники (для option-ов)
        $paymentTypeOptions     = self::buildOptionsFromRows($references->paymentType->rows ?? [], 'id', 'name', '', 'Выберите тип оплаты');
        $statusesOptions        = self::buildOptionsFromRows($references->statuses->states ?? [], 'id', 'name', '', 'Выберите статус');
        $organizationsOptions   = self::buildOptionsFromRows($references->organizations->rows ?? [], 'id', 'name', '', 'Выберите организацию');
        $channelsOptions        = self::buildOptionsFromRows($references->channels->rows ?? [], 'id', 'name', '', 'Выберите канал связи', true);
        $projectsOptions        = self::buildOptionsFromRows($references->projects->rows ?? [], 'id', 'name', '', 'Выберите проект');
        $paymentStatusOptions   = self::buildOptionsFromRows($references->paymentStatuses->rows ?? [], 'id', 'name', '', 'Выберите статус оплаты', true);

        // delivery: отдельный helper, т.к. у тебя есть "byhand" и важно корректно выставлять selected
        $deliveryBaseRows = $references->deliveryServices->rows ?? [];

        // кассы
        $cashRegisterArr  = CashRegister::getCashRegisterList();
        $cashRegisterBase = self::buildOptionsFromScalarList($cashRegisterArr, '', 'Выберите кассу', true, '0', 'Нет');

        // Какие проекты поддерживаем
        $simpleProjects = [
            '842c5548-c90c-11f0-0a80-1aee002c13e9', // 🟢 Halyk Market
            '5f351348-d269-11f0-0a80-15120016d622', // 🔴 Kaspi Accio
            '431a8172-d26a-11f0-0a80-0f110016cabd', // 🔴 Tutto Capsule Kaspi
            '98777142-d26a-11f0-0a80-1be40016550a', // 🔴 Ital Trade
            'a463b9da-d26c-11f0-0a80-1a6b0016a57a', // 🔵 Wolt
            'a4481c66-d274-11f0-0a80-0f110017905c', // 🟣 Forte Market
            '341ee0eb-d269-11f0-0a80-0cf20015f0d3', // 📍 Accio
        ];

        $legalProjectId       = '6b625db1-d270-11f0-0a80-1512001756b3'; // 💎 Юридическое лицо
        $storeProjectId       = '8fe86883-d275-11f0-0a80-15120017c4b6'; // 🔥 Store
        $accioStoreProjectId  = 'c4bd7d52-d276-11f0-0a80-17910017cc0c'; // ♥️ Accio Store

        $emptyProjects = [
        ];

        // ---------- ПУСТО ----------
        if (in_array($mid, $emptyProjects, true)) {
            return '<form name="order-config"></form>';
        }

        // ---------- НЕ ЮРИК: 1 конфиг (action_type=0) ----------
        if (in_array($mid, $simpleProjects, true)) {
            $actionType = 0;
            $config = self::getConfig($mid, $actionType);

            $deliveryOptions   = self::buildDeliveryOptions($deliveryBaseRows, $config->delivery_service ?? '');
            $cashRegisterOpts  = self::applySelectedToOptions($cashRegisterBase, $config->cash_register ?? '');
            $legalAccountsOpts = self::buildLegalAccountsOptions($moysklad, $config->organization ?? '', $config->legal_account ?? '');

            $form  = '<form name="order-config">';
            $form .= self::renderSection(
                $config,
                'Настройки',
                '', // prefix пустой -> обычные name="payment-type"
                $paymentTypeOptions,
                $statusesOptions,
                $organizationsOptions,
                $channelsOptions,
                $projectsOptions,
                $paymentStatusOptions,
                $deliveryOptions,
                $legalAccountsOpts,
                $cashRegisterOpts
            );

            $form .= '<div class="form-group submits">
                        <input type="hidden" name="action_type" value="0" />
                        <input type="hidden" name="project" value="' . self::e($mid) . '" />
                        <button type="submit" class="btn btn-sm btn-dark">Сохранить</button>
                      </div>
                    </form>';

            return $form;
        }

        // ---------- ЮРИК: ОДНА форма, несколько configs[action_type][...] ----------
        if ($mid === $legalProjectId) {
            $sections = [
                0 => '0. Сайт - Безналичный расчет',
                1 => '1. Сайт - Банковская карта',
                2 => '2. Сайт - Наличные',
                3 => '3. Вручную - Банковская карта',
                4 => '4. Вручную - Наличные',
            ];

            $form  = '<form name="order-config">';
            $form .= '<input type="hidden" name="project" value="' . self::e($mid) . '" />';

            foreach ($sections as $actionType => $title) {
                $config = self::getConfig($mid, (int)$actionType);

                $deliveryOptions   = self::buildDeliveryOptions($deliveryBaseRows, $config->delivery_service ?? '');
                $cashRegisterOpts  = self::applySelectedToOptions($cashRegisterBase, $config->cash_register ?? '');
                $legalAccountsOpts = self::buildLegalAccountsOptions($moysklad, $config->organization ?? '', $config->legal_account ?? '');

                $prefix = 'configs[' . (int)$actionType . ']'; // ключевой момент

                $form .= self::renderSection(
                    $config,
                    $title,
                    $prefix,
                    $paymentTypeOptions,
                    $statusesOptions,
                    $organizationsOptions,
                    $channelsOptions,
                    $projectsOptions,
                    $paymentStatusOptions,
                    $deliveryOptions,
                    $legalAccountsOpts,
                    $cashRegisterOpts
                );
            }

            $form .= '<div class="form-group submits">
                        <button type="submit" class="btn btn-sm btn-dark">Сохранить всё</button>
                      </div>
                    </form>';

            return $form;
        }

        // ---------- STORE: ОДНА форма, несколько configs[action_type][...] ----------
        if ($mid === $storeProjectId) {
            $sections = [
                0 => '0. Вручную - Наличными',
                1 => '1. Вручную - Kaspi QR',
                2 => '2. Вручную - Банковской картой',
                3 => '3. Вручную - Forte Online Payment',
                4 => '4. Сайт - Forte Online Payment',
                5 => '5. Сайт - Kaspi QR',
            ];

            $form  = '<form name="order-config">';
            $form .= '<input type="hidden" name="project" value="' . self::e($mid) . '" />';

            foreach ($sections as $actionType => $title) {
                $config = self::getConfig($mid, (int)$actionType);

                $deliveryOptions   = self::buildDeliveryOptions($deliveryBaseRows, $config->delivery_service ?? '');
                $cashRegisterOpts  = self::applySelectedToOptions($cashRegisterBase, $config->cash_register ?? '');
                $legalAccountsOpts = self::buildLegalAccountsOptions($moysklad, $config->organization ?? '', $config->legal_account ?? '');

                $prefix = 'configs[' . (int)$actionType . ']';

                $form .= self::renderSection(
                    $config,
                    $title,
                    $prefix,
                    $paymentTypeOptions,
                    $statusesOptions,
                    $organizationsOptions,
                    $channelsOptions,
                    $projectsOptions,
                    $paymentStatusOptions,
                    $deliveryOptions,
                    $legalAccountsOpts,
                    $cashRegisterOpts
                );
            }

            $form .= '<div class="form-group submits">
                        <button type="submit" class="btn btn-sm btn-dark">Сохранить всё</button>
                      </div>
                    </form>';

            return $form;
        }

        // ---------- ♥️ ACCIO STORE: ОДНА форма, несколько configs[action_type][...] ----------
        if ($mid === $accioStoreProjectId) {
            $sections = [
                0 => '0. Вручную - Kaspi Link',
                1 => '1. Вручную - 🟣 Forte Online Payment',
                2 => '2. Сайт - 🟣 Forte Online Payment',
                3 => '3. Сайт - Kaspi QR',
            ];

            $form  = '<form name="order-config">';
            $form .= '<input type="hidden" name="project" value="' . self::e($mid) . '" />';

            foreach ($sections as $actionType => $title) {
                $config = self::getConfig($mid, (int)$actionType);

                $deliveryOptions   = self::buildDeliveryOptions($deliveryBaseRows, $config->delivery_service ?? '');
                $cashRegisterOpts  = self::applySelectedToOptions($cashRegisterBase, $config->cash_register ?? '');
                $legalAccountsOpts = self::buildLegalAccountsOptions($moysklad, $config->organization ?? '', $config->legal_account ?? '');

                $prefix = 'configs[' . (int)$actionType . ']';

                $form .= self::renderSection(
                    $config,
                    $title,
                    $prefix,
                    $paymentTypeOptions,
                    $statusesOptions,
                    $organizationsOptions,
                    $channelsOptions,
                    $projectsOptions,
                    $paymentStatusOptions,
                    $deliveryOptions,
                    $legalAccountsOpts,
                    $cashRegisterOpts
                );
            }

            $form .= '<div class="form-group submits">
                        <button type="submit" class="btn btn-sm btn-dark">Сохранить всё</button>
                      </div>
                    </form>';

            return $form;
        }


        // Если проект неизвестный — вернём пустую форму
        return '<form name="order-config"></form>';
    }

    /* ===================== HELPERS ===================== */

    private static function getConfig(string $project, int $actionType): ?OrdersConfigTable
    {
        return OrdersConfigTable::findOne(['project' => $project, 'action_type' => $actionType]);
    }

    /**
     * Рендер секции настроек.
     * $prefix:
     *   - '' => обычные name="payment-type"
     *   - 'configs[2]' => name="configs[2][payment-type]"
     */
    private static function renderSection(
        ?OrdersConfigTable $config,
        string $title,
        string $prefix,
        string $paymentTypeOptions,
        string $statusesOptions,
        string $organizationsOptions,
        string $channelsOptions,
        string $projectsOptions,
        string $paymentStatusOptions,
        string $deliveryOptions,
        string $legalAccountsOptions,
        string $cashRegisterOptions
    ): string {
        $name = function(string $field) use ($prefix): string {
            return $prefix === '' ? $field : ($prefix . '[' . $field . ']');
        };

        $selectedPaymentType   = $config->payment_type ?? '';
        $selectedStatus        = $config->status ?? '';
        $selectedOrg           = $config->organization ?? '';
        $selectedChannel       = $config->channel ?? '';
        $selectedProjectField  = $config->project_field ?? '';
        $selectedPayStatus     = $config->payment_status ?? '';
        $selectedCashRegister  = $config->cash_register ?? '';
        $selectedFiscal        = $config->fiscal ?? '';

        // Применяем selected к общим option-строкам
        $paymentType = self::applySelectedToOptions($paymentTypeOptions, $selectedPaymentType);
        $statuses    = self::applySelectedToOptions($statusesOptions, $selectedStatus);
        $orgs        = self::applySelectedToOptions($organizationsOptions, $selectedOrg);
        $channels    = self::applySelectedToOptions($channelsOptions, $selectedChannel);
        $projects    = self::applySelectedToOptions($projectsOptions, $selectedProjectField);
        $payStatuses = self::applySelectedToOptions($paymentStatusOptions, $selectedPayStatus);
        $cashRegs    = self::applySelectedToOptions($cashRegisterOptions, $selectedCashRegister);

        $fiscalYesId = 'c3c0ee4f-a4e7-11eb-0a80-075b00176e05';
        $fiscalNoId  = 'c919fb37-a4e7-11eb-0a80-00dd00166ffd';

        $html  = '<section class="project-type-el">';
        $html .= '<h3>' . self::e($title) . '</h3>';

        $html .= '
            <div class="form-group mb-3 col-2">
              <label class="form-label">Тип оплаты</label>
              <select class="form-control form-select" name="' . self::e($name('payment-type')) . '" required>
                ' . $paymentType . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Нужен ли фискальный чек?</label>
              <select class="form-control form-select" name="' . self::e($name('fiskal')) . '" required>
                <option value="">Выберите</option>
                <option value="byhand"' . ($selectedFiscal === 'byhand' ? ' selected' : '') . '>Устанавливается вручную</option>
                <option value="' . self::e($fiscalYesId) . '"' . ($selectedFiscal === $fiscalYesId ? ' selected' : '') . '>Да</option>
                <option value="' . self::e($fiscalNoId) . '"'  . ($selectedFiscal === $fiscalNoId  ? ' selected' : '') . '>Нет</option>
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Статус</label>
              <select class="form-control form-select" name="' . self::e($name('status')) . '" required>
                ' . $statuses . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Организация</label>
              <select class="form-control form-select organization-select" name="' . self::e($name('organization')) . '" required>
                ' . $orgs . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Счет юридического лица</label>
              <select class="form-control form-select legalaccountnumber-select" name="' . self::e($name('legalaccountnumber')) . '" required>
                ' . $legalAccountsOptions . '
              </select>
              <div class="hint">Перед выбором счета выберите организацию</div>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Канал связи</label>
              <select class="form-control form-select" name="' . self::e($name('channel')) . '" required>
                ' . $channels . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Поле "Выбрать проект"</label>
              <select class="form-control form-select" name="' . self::e($name('project-field')) . '" required>
                ' . $projects . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Статус оплаты</label>
              <select class="form-control form-select" name="' . self::e($name('payment-status')) . '" required>
                ' . $payStatuses . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Служба доставки</label>
              <select class="form-control form-select" name="' . self::e($name('delivery-service')) . '" required>
                ' . $deliveryOptions . '
              </select>
            </div>

            <div class="form-group mb-3 col-2">
              <label class="form-label">Касса</label>
              <select class="form-control form-select" name="' . self::e($name('cash-register')) . '" required>
                ' . $cashRegs . '
              </select>
            </div>
        ';

        $html .= '</section>';

        return $html;
    }

    /**
     * Строим <option> из массива объектов rows.
     * Можно включить byhand.
     */
    private static function buildOptionsFromRows(array $rows, string $idField, string $nameField, string $selectedValue, string $placeholder, bool $withByHand = false): string
    {
        $html = '<option value="">' . self::e($placeholder) . '</option>';

        if ($withByHand) {
            $html .= '<option value="byhand"' . ($selectedValue === 'byhand' ? ' selected' : '') . '>Устанавливается вручную</option>';
        }

        foreach ($rows as $row) {
            $id   = (string)($row->$idField ?? '');
            $name = (string)($row->$nameField ?? '');
            if ($id === '') { continue; }

            $sel = ($selectedValue !== '' && $selectedValue === $id) ? ' selected' : '';
            $html .= '<option value="' . self::e($id) . '"' . $sel . '>' . self::e($name) . '</option>';
        }

        return $html;
    }

    /**
     * Для списков строк (кассы).
     * $withByHand - добавить option "byhand"
     * $extraValue/$extraLabel - например "0" => "Нет"
     */
    private static function buildOptionsFromScalarList(array $list, string $selectedValue, string $placeholder, bool $withByHand = false, string $extraValue = '', string $extraLabel = ''): string
    {
        $html = '<option value="">' . self::e($placeholder) . '</option>';

        if ($extraValue !== '') {
            $html .= '<option value="' . self::e($extraValue) . '"' . ($selectedValue === $extraValue ? ' selected' : '') . '>' . self::e($extraLabel) . '</option>';
        }

        if ($withByHand) {
            $html .= '<option value="byhand"' . ($selectedValue === 'byhand' ? ' selected' : '') . '>Устанавливается вручную</option>';
        }

        foreach ($list as $val) {
            $val = (string)$val;
            if ($val === '') { continue; }
            $sel = ($selectedValue !== '' && $selectedValue === $val) ? ' selected' : '';
            $html .= '<option value="' . self::e($val) . '"' . $sel . '>' . self::e($val) . '</option>';
        }

        return $html;
    }

    /**
     * deliveryServices + byhand.
     */
    private static function buildDeliveryOptions(array $deliveryRows, string $selectedValue): string
    {
        $html  = '<option value="">Выберите службу доставки</option>';
        $html .= '<option value="byhand"' . ($selectedValue === 'byhand' ? ' selected' : '') . '>Устанавливается вручную</option>';

        foreach ($deliveryRows as $row) {
            $id   = (string)($row->id ?? '');
            $name = (string)($row->name ?? '');
            if ($id === '') { continue; }

            $sel = ($selectedValue !== '' && $selectedValue === $id) ? ' selected' : '';
            $html .= '<option value="' . self::e($id) . '"' . $sel . '>' . self::e($name) . '</option>';
        }

        return $html;
    }

    /**
     * Счета организации.
     * Если org пустой — просто "Выберите счет".
     * Если есть org и счета — добавляем "byhand".
     */
    private static function buildLegalAccountsOptions(Moysklad $moysklad, string $organizationId, string $selectedValue): string
    {
        $html = '<option value="">Выберите счет</option>';

        if ($organizationId === '') {
            return $html;
        }

        $accounts = $moysklad->getOrganizationAccounts($organizationId);
        $rows = $accounts->rows ?? [];

        if (empty($rows)) {
            // Организация выбрана, но счетов нет
            return $html;
        }

        $html .= '<option value="byhand"' . ($selectedValue === 'byhand' ? ' selected' : '') . '>Устанавливается вручную</option>';

        foreach ($rows as $row) {
            $id  = (string)($row->id ?? '');
            $acc = (string)($row->accountNumber ?? '');
            if ($id === '') { continue; }

            $sel = ($selectedValue !== '' && $selectedValue === $id) ? ' selected' : '';
            $html .= '<option value="' . self::e($id) . '"' . $sel . '>' . self::e($acc) . '</option>';
        }

        return $html;
    }

    /**
     * Проставляет selected в строке option-ов.
     * (Ожидаем, что option-ы построены без selected, кроме byhand/extra — всё равно корректно перезатрём.)
     */
    private static function applySelectedToOptions(string $optionsHtml, string $selectedValue): string
    {
        // Быстрый выход
        if ($selectedValue === '' || $optionsHtml === '') {
            return $optionsHtml;
        }

        // Убираем все selected, затем ставим на нужный value
        $optionsHtml = preg_replace('/\sselected\b/u', '', $optionsHtml);

        $value = preg_quote($selectedValue, '/');
        $optionsHtml = preg_replace('/(<option\s+[^>]*value="' . $value . '"[^>]*)>/u', '$1 selected>', $optionsHtml, 1);

        return $optionsHtml;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
