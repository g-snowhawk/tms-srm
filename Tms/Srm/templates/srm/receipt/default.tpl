{% extends "master.tpl" %}

{% block head %}
  <template id="mailer-template">
    <div id="mailer-container">
      <form action="{{ form.action }}" method="post" enctype="application/x-www-form-urlencoded">
        <input type="hidden" name="stub">
        <input type="hidden" name="mode" value="srm.receipt.receive:sendmail">
        <input type="hidden" name="from">
        <input type="hidden" name="reply-to">
        <div class="controller">
          <input type="reset" value="キャンセル">
          <input type="submit" value="送信">
        </div>
        <div class="flex"><label>宛先</label><input type="text" name="to" required></div>
        <div class="flex"><label>件名</label><input type="text" name="subject" required></div>
        <div class="grow"><textarea name="mail_body" required></textarea></div>
        <div class="plain"><input type="hidden" name="pdf_path"><input type="hidden" name="attachment_name"></div>
      </form>
    </div>
  </template>
{% endblock %}

{% block main %}
<input type="hidden" name="mode" value="srm.receipt.receive:remove">
<div class="flex-row-box">
  <div class="grow-box">
    <div class="wrapper section-list">
      <h1>{{ receiptName }}一覧</h1>
      {% for unit in receipts %}
        <section{% if unit.unavailable == "1"%} class="unavailable"{% endif %}>
          <h2>{{ unit.subject }}</h2>
          <h3>{{ unit.company }}</h3>
          <p>No.{{ unit.receipt_number }}&nbsp;発行日：{{ unit.issue_date|date('Y年m月d日') }}{% if mail == 'enable' and unit.draft != '1' and unit.unavailable != '1' %}&nbsp;<a href="?mode=srm.receipt.response:mailer&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}" class="run-mailer">E-mail</a>{% endif %}</p>
          {% if typeOf == 'bill' or typeOf == 'receipt' %}
            <p class="bill-info{% if unit.unavailable == '1' %} strike{% endif %}">
            支払期限：<i>{{ unit.due_date|date('Y年m月d日') }}</i><br>
            {% if unit.draft == '1' %}
              ？入金日：<span>---</span>
            {% elseif unit.unavailable == '1' %}
              ×入金日：
            {% elseif unit.collected == 1 %}
              ●入金日：<strong>未入金</strong>
            {% elseif unit.collected == 2 %}
              ○入金日：<b>入金待ち</b>
            {% elseif unit.receipt is not empty %}
              ◎入金日：<i>{{ unit.receipt|date('Y年m月d日') }}</i>
            {% endif %}
            </p>
          {% endif %}
          <nav class="controls flex-block">
            <div>
              {% if unit.draft != '1' and unit.unavailable != '1' and duplicateTo is defined %}
                {% for item in duplicateTo %}
                  <a href="?mode=srm.receipt.response:edit&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}&amp;cp={{ item.id }}">{{ item.label }}</a>
                {% endfor %}
              {% endif %}
            </div>
            <div>
              {% if apps.hasPermission('srm.update') %} 
              <a href="?mode=srm.receipt.response:edit&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}{% if unit.draft == '1'%}&amp;draft=1{% endif %}">{{ unit.draft != '1' ? '明細表示' : '編集' }}</a>
              {% endif %}
              {% if unit.unavailable == '1' %}
                <span>無効</span>
              {% elseif unit.draft != '1' %}
                <a href="?mode=srm.receipt.response:download-pdf&id={{ unit.issue_date|date('Y-m-d') ~ ':' ~ unit.receipt_number }}" target="_blank">PDF</a>
              {% else %}
                <span>下書き</span>
              {% endif %}
            </div>
          </nav>
        </section>
      {% else %}
        <p>未だ{{ receiptName }}が登録されていません</p>
      {% endfor %}
    </div>
  </div>
  <div class="fixed-box">
    <div class="explorer-list">
      <div class="footer-controls">
        <nav class="links flexbox">
          {% if apps.hasPermission('srm.create') %} 
          <a href="?mode=srm.receipt.response:edit"><mark>＋</mark>新規{{ receiptName }}作成</a>
          {% endif %}
          <input type="text" name="search_query" id="search-query-1" class="search-query"{% if queryString %} value="{{ queryString }}"{% endif %}>
          <button type="reset" class="clear-search">×</button>
          <a href="?mode=srm.receipt.response:search-options" class="normal-link options subform-opener">検索オプション</a>
        </nav>
        <nav class="pagination">
          {% include 'pagination.tpl' with {'columnCount':'9'} %}
        </nav>
      </div>
    </div>
  </div>
</div>
{% endblock %}

{% block pagefooter %}
  <script src="/script/srm/receipt.js"></script>
{% endblock %}
