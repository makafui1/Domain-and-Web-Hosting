<div class="row">
    <div class="col-md-12 col-sm-12">
        <div class="box">
        <div class="box-header">
        <i class="fa fa-server"></i> <?=lang('plugins')?>
           </div>
            <div class="box-body">
                <table id="table-templates-2" class="table table-striped b-t b-light text-sm AppendDataTables dataTable no-footer">
                    <thead>
                        <tr>
                            <th>Plugin</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>URI</th>
                            <th>Version</th>
                            <th>Description</th>
                            <th>Author</th> 
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($plugins as $k => $p): ?>
                        <tr>
                            <td><?php echo $p->name; ?></td>
                            <td><?php echo $p->category; ?></td>
                            <td><?= ($p->status ? 'Enabled' : 'Disabled'); ?></td>
                            <td><?='<a href=' . $p->uri . '" target="_blank">' . $p->uri . '</a>'; ?></td>
                            <td><?= $p->version; ?></td>
                            <td><?= $p->description; ?></td>
                            <td><?='<a href="http://' . $p->author_uri . '" target="_blank">' . $p->author . '</a>'; ?></td> 
                            <td> 
                            <?php if ($p->status == 1) { ?>
                            <a class="btn btn-primary btn-sm trigger" href="<?= site_url('plugins/config?plugin=' . $p->system_name) ?>" data-toggle="ajaxModal">Settings</a> 
                            <?php } else { ?>
                                <a class="btn btn-warning btn-sm trigger" href="<?= site_url('plugins/uninstall/' . $p->system_name) ?>" data-toggle="ajaxModal">Uninstall</a> 
                            <?php } if ($p->status == 0) { ?><a
                                class="btn btn-success btn-sm" href="<?= site_url('plugins/activate/' . $p->system_name) ?>">
                                Activate</a><?php } else { ?>
                                <a class="btn btn-warning btn-sm" href="<?= site_url('plugins/deactivate/' . $p->system_name) ?>" href="<?php echo site_url('plugin/deactivate/' . $p->system_name) ?>">
                                Deactivate</a><?php } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

