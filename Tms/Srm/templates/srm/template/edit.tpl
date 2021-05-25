{% extends "master.tpl" %}

{% block main %}
  <p id="backlink"><a href="?mode=srm.template.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>帳票基本設定</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">帳票名</label>
      <input type="text" name="title" id="title" value="{{ post.title }}" placeholder="分かりやすい名前をつけてください">
    </div>
    {% if err.vl_line == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_line == 1 %} invalid{% endif %}">
      <label for="line">行数</label>
      <input type="text" name="line" id="line" value="{{ post.line }}" maxlength="2" class="ta-r">
    </div>
    {% if err.vl_priority == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_priority == 1 %} invalid{% endif %}">
      <label for="line">表示順</label>
      <input type="text" name="priority" id="priority" value="{{ post.priority }}" maxlength="2" class="ta-r">
    </div>

    {% if err.vl_pdf_mapper == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_pdf_mapper == 1 %} invalid{% endif %}">
      <label for="pdf_mapper">PDFマップ XML</label>
      <textarea name="pdf_mapper" id="pdf_mapper" wrap="off">{{ post.pdf_mapper }}</textarea>
    </div>

    {% if err.vl_base_pdf_single == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_base_pdf_single == 1 %} invalid{% endif %}">
      <label for="base_pdf_single">単一ページ用PDF</label>
      <label class="file"><input type="file" name="base_pdf_single" id="base_pdf_single" value="{{ post.base_pdf_single }}" accept="application/pdf"></label>
    </div>

    {% if err.vl_base_pdf_multiple == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_base_pdf_multiple == 1 %} invalid{% endif %}">
      <label for="base_pdf_multiple">複数ページ用PDF</label>
      <label class="file"><input type="file" name="base_pdf_multiple" id="base_pdf_multiple" value="{{ post.base_pdf_multiple }}" accept="application/pdf"></label>
    </div>

    <div class="fieldset{% if err.vl_mail_template == 1 %} invalid{% endif %}">
      <label for="mail_template">メール雛形</label>
      <textarea name="mail_template" id="mail_template" wrap="off">{{ post.mail_template }}</textarea>
    </div>

    <div class="metadata">
      登録日：{{ post.create_date|date('Y年n月j日 H:i') }}<input type="hidden" name="create_date" value="{{ post.create_date }}"><br>
      更新日：{{ post.modify_date|date('Y年n月j日 H:i') }}<input type="hidden" name="modify_date" value="{{ post.modify_date }}"><br>
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="登録">
      <input type="hidden" name="mode" value="srm.template.receive:save">
      <input type="hidden" name="id" value="{{ post.id }}">
    </div>
  </div>
{% endblock %}
