{% extends "master.tpl" %}

{% block head %}
<template id="preview-block">
  <div id="preview-container">
    <object data="" class="preview-content"></object>
    <div class="image-frame">
      <img src="" class="preview-content">
    </div>
    <div class="progress-frame">
      <progress max="100">プレビュー作成中...</progress>
    </div>
    <div class="preview-controller">
      <a href="#" class="close-button">閉じる</a>
    </div>
  </div>
</template>
{% endblock %}

{% block main %}
  {% if pageCount > 1 %}
    {% set currentPage = post.page_number is defined ? post.page_number : 1 %}
  {% endif %}
  <input type="hidden" name="mode" value="srm.receipt.receive:save">
  <input type="hidden" name="draft" value="{% if post.draft is defined %}{{ post.draft }}{% else %}1{% endif %}">
  <p id="backlink"><a href="?mode=srm.receipt.response">{{ receiptName }}一覧に戻る</a></p>
  <div class="wrapper">
    <h1>{{ receiptName }}編集</h1>
    <div class="receipt-form">
      <div class="flex-box">
        <p>
          {% if post.receipt_number is defined %}
          <span id="receipt-number">No.&thinsp;{{ post.receipt_number }}<input type="hidden" name="receipt_number" value="{{ post.receipt_number }}">
            {%- if post.page_number is defined or pageCount > 1 %}
              <span id="page-number">&nbsp;:&nbsp;p{{ currentPage }}<input type="hidden" name="page_number" value="{{ post.page_number }}"></span>
            {% endif -%}
          </span><br>
          {% endif %}
          <input type="date" name="issue_date" placeholder="発行日" value="{{ post.issue_date|date('Y-m-d') }}" required class="ta-r">
          {% for field in extendedFields %}
            {% if loop.first %}
            <span class="extended-fields meta-data{% if linked == 'yes' %} linked{% endif %}">
            {% endif %}
            {% if 'before-linked' not in field.class or linked != 'yes' %}
              {% if field.type == 'text' or field.type == 'date' %}
              <input type="{{ field.type }}" name="{{ field.name }}" value="{{ post[field.name] }}" placeholder="{{ field.label }}"{%- for key in field|keys %}{% if key not in ['type','name','label'] %} {{ key }}="{{ field[key] }}"{% endif %}{% endfor -%}><br>
              {% elseif field.type == 'checkbox' %}
              <label{% if 'nowrap' in field.class %} class="nowrap"{% endif %}><input type="checkbox" name="{{ field.name }}" value="{{ field.value }}"{% if post[field.name] == field.value %} checked{% endif %}{%- for key in field|keys %}{% if key not in ['type','name','label','value'] %} {{ key }}="{{ field[key] }}"{% endif %}{% endfor -%}>{{ field.label }}</label><br>
              {% elseif field.type == 'textarea' %}
              <textarea name="{{ field.name }}" placeholder="{{ field.label }}">{{ field.defaultValue }}</textarea><br>
              {% elseif field.type == 'select' %}
                {% set options = apps.callFromTemplate(field.source, field.name) %}
                <select name="{{ field.name }}"{% if field.class is defined %} class="{{ field.class }}"{% endif %}>
                  <option value="">{{ field.label }}</option>
                  {% for option in options %}
                  <option value="{{ option.value }}"{% if post[field.name] == option.value %} selected{% endif %}>{{ option.label }}</option>
                  {% endfor %}
                  {% if field.class == 'with-other' %}
                    <option value="" data-other="isOther">選択肢追加...</option>
                  {% endif %}
                </select><br>
              {% elseif field.type == 'script' %}
              <script src="{{ field.src }}"{% if field.async is not empty %} async{% endif %}></script>
              {% endif %}
            {% endif %}
            {% if loop.last %}
            </span>
            {% endif %}
          {% endfor %}
        </p>
        <p>
          <span id="client-data"><input type="text" name="company" value="{{ post.company }}" placeholder="取引先名" tabindex="1" required></span><br>
          <input type="text" name="division" value="{{ post.division }}" placeholder="部署"><br>
          <input type="text" name="fullname" value="{{ post.fullname }}" placeholder="担当者"><br>
          <input type="text" name="zipcode" value="{{ post.zipcode }}" placeholder="郵便番号" maxlength="8"><br>
          <input type="text" name="address1" value="{{ post.address1 }}" placeholder="住所"><br>
          <input type="text" name="address2" value="{{ post.address2 }}" placeholder="建物名等"><br>
        </p>
      </div>
      <p><input type="text" name="subject" value="{{ post.subject }}" placeholder="件名" required></p>
      <table class="receipt-detail">
        <thead>
          <tr>
            <td>内容</td>
            <td>軽</td>
            <td>単価</td>
            <td>数量</td>
            <td>単位</td>
            <td>金額</td>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <td colspan="2" rowspan="5"><textarea name="note" class="fixed-size-to-parent" placeholder="備考">{{ post.note }}</textarea></td>
            <td colspan="3"><div class="label">小計</div></td>
            <td><div class="value" id="subtotal">{{ subtotal }}</div></td>
          </tr>
          <tr>
            <td colspan="3"><div class="label">消費税</div></td>
            <td><div class="value" id="tax">{{ tax }}</div></td>
          </tr>
          <tr>
            <td colspan="3"><input type="text" name="additional_1_item" value="{{ post.additional_1_item }}"></td>
            <td><input type="number" name="additional_1_price" value="{{ post.additional_1_price }}"></td>
          </tr>
          <tr>
            <td colspan="3"><input type="text" name="additional_2_item" value="{{ post.additional_2_item }}"></td>
            <td><input type="number" name="additional_2_price" value="{{ post.additional_2_price }}"></td>
          </tr>
          <tr>
            <td colspan="3"><div class="label">合計</div></td>
            <td><div class="value" id="total">{{ total }}</div></td>
          </tr>
        </tfoot>
        <tbody>
          {# set carried = carryForward is defined and carryForward > 0 ? 1 : 0 #}
          {% set carried = post.page_number > 1 and carryForwardTitle is defined ? 1 : 0 %}
          {% for i in 1..lineCount %}
            {% set attr = (i == 2 and carried == 1) or i == 1 ? ' required' : '' %}
            {% if i == 1 and carried == 1 %}
              <tr class="carry-forward">
                <td>{{ carryForwardTitle }}</td>
                <td class="ta-c"><span>*</span></td>
                <td>
                  <input type="hidden" name="carry_forward" value="{{ carryForward }}">
                  <input type="hidden" name="price[1]" value="0">
                </td>
                <td>&nbsp;</td>
                <td><input type="hidden" name="carry_forward_tax" value="{{ carryForwardTax }}">&nbsp;</td>
                <td><div id="sum-0"></div></td>
              </tr>
            {% else %}
              <tr>
                <td><input type="text" name="content[{{ i }}]" value="{{ post.content[i] }}"{{ attr }}></td>
                <td class="ta-c"><label class="hidden-checkbox"><input type="checkbox" name="reduced_tax_rate[{{ i }}]" value="1"{% if post.reduced_tax_rate[i] == '1' %} checked{% endif %}><span>*</span></label></td>
                <td><input type="number" name="price[{{ i }}]" value="{{ post.price[i] }}"></td>
                <td><input type="number" name="quantity[{{ i }}]" value="{{ post.quantity[i] }}"></td>
                <td><input type="text" name="unit[{{ i }}]" value="{{ post.unit[i] }}"></td>
                <td><div id="sum-{{ i }}"></div></td>
              </tr>
            {% endif %}
          {% endfor %}
        </tbody>
      </table>

      {% if pageCount > 1 %}
      <nav class="page-nav">
        {% for i in range(1, pageCount) %}
          {% if post.draft != '0' %}
            <button type="submit" name="move_page" value="{{ i }}" class="page-button{% if currentPage == i %} current{% endif %}">{{ i }}</button>
          {% else %}
            <a href="?mode=srm.receipt.response:edit&id={{ post.issue_date }}:{{ post.receipt_number }}:{{ i }}" class="page-button{% if currentPage == i %} current{% endif %}">{{ i }}</a>
          {% endif %}
        {% endfor %}
      </nav>
      {% endif %}

      <div class="notice">
        <ul>
          <li id="tax-rate" data-rate="{{ tax_rate }}">消費税率は<i class="under-line">{{ tax_rate * 100 }}%</i>で計算しています</li>
          {% if reduced_tax_rate is not empty and reduced_tax_rate > 0 %}
            <li id="reduced-tax-rate" data-rate="{{ reduced_tax_rate }}">軽減税率は<i class="under-line">{{ reduced_tax_rate * 100 }}%</i>で計算しています</li>
          {% endif %}
        </ul>
      </div>
    </div>
    <div class="form-footer">
      {% if post.draft != '0' %}
        <div class="separate-block">
          <span>
            <input type="submit" name="s1_submit" value="発行">
            <input type="submit" name="s1_delete" value="削除" data-confirm="この{{ receiptName }}を削除します。取り消しはできませんが、よろしいですか？">
            {# if enable_preview #}<br><input type="button" name="s2_preview" value="プレビュー">{# endif #}
          </span>
          <span>
            <a href="?mode=srm.receipt.response" class="button">キャンセル</a>
            <input type="submit" name="s1_draft" value="下書き保存">
            <input type="submit" name="s1_addpage" value="次葉の追加">
          </span>
        </div>
      {% else %}
        <div class="adjacent-block">
          <p>この{{ receiptName }}は確定済みです。これ以上変更できません</p>
          <p>
            <a href="?mode=srm.receipt.response" class="like-button">キャンセル<a>
            <a href="?mode=srm.receipt.response:download-pdf&amp;id={{ post.issue_date|date('Y-m-d') ~ ':' ~ post.receipt_number }}" target="_blank" class="like-button">PDF</a>
            {% if post.unavailable == '0' %}
            <a href="?mode=srm.receipt.receive:unavailable&amp;id={{ post.issue_date|date('Y-m-d') ~ ':' ~ post.receipt_number }}" class="like-button availables" data-prompt="無効にする理由" data-alert="理由がなければ無効にできません">無効にする</a>
            {% else %}
            <a href="?mode=srm.receipt.receive:available&amp;id={{ post.issue_date|date('Y-m-d') ~ ':' ~ post.receipt_number }}" class="like-button availables">有効にする</a>
            {% endif %}
          </p>
        </div>
      {% endif %}
    </div>
  </div>
{% endblock %}

{% block pagefooter %}
  <script src="/script/srm/receipt_editor.js"></script>
{% endblock %}
