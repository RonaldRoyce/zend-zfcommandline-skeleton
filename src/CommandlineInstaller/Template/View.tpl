<style>
</style>

<div class="content">
        <div class="container-fluid">
                <div class="row">
                        <div class="col-md-12">
                                <form method="post" action="" autocomplete="off" class="form-horizontal">
                                <div class="card ">
                                        <div class="card-header card-header-primary">
                                                <h4 class="card-title">Title</h4>
                                        </div>
                                        <div class="card-body ">
                                                <div class="row">
                                                        <table id="report-table" style="table-layout: fixed;" class="table table-striped table-bordered table-sm pagetable">
                                                                <thead>
                                                                        <tr>
                                                                        </tr>
                                                                </thead>
                                                                <tbody>
                                                                <?php
                                                                foreach ($this->data as $row)
                                                                {
                                                                ?>
                                                                        <tr>
                                                                        </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                                </tbody>
                                                        </table>
                                                </div>
                                        </div>
                                </div>
                                </form>
                        </div>
                </div>
        </div>
</div>

<script src="/js/jquery-3.1.0.min.js"></script>
<script src="/js/jquery.nicescroll.min.js"></script>
<script src="/js/jquery-ui.min.js"></script>
<script src="/js/bootstrap-datepicker.min.js"></script>
<script src="/js/chosen.jquery.js"></script>
<script src="/js/multiple-emails.js"></script>

<script src="/js/reportmaint/reportmanagement.js"></script>

<script>
        $(document).ready(function() {
        });
</script>

