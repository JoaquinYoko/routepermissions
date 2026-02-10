<?php
// ... (mismo encabezado de licencia que ya tienes) ...
?>
<div class="container-fluid">
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display no-border">
					<h1><?php echo _("Route Permissions")?></h1>
                    
                    <?php if(!empty($message)):?>
                        <div class="alert alert-success alert-dismissable" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <h3><i class="fa fa-info-circle" aria-hidden="true"></i> <?=_("Messages")?></h3>
                            <?php echo $message?>
                        </div>
                    <?php endif;?>
                    
                    <?php if(!empty($errormessage)):?>
                        <div class="alert alert-warning alert-dismissable" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <h3><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <?=_("Errors")?></h3>
                            <?php echo $errormessage?>
                        </div>
                    <?php endif;?>

                    <h4><?=htmlspecialchars(_("Bulk Changes"))?></h4>
					<p>
						<?=_("Select a route and select <b>Allow</b> or <b>Deny</b> to set permissions for the entered extensions...")?>
					</p>
					
					<form method="post">
						<table class="table table-bordered">
							<thead>
								<tr>
									<th><?=_("Route")?></th>
									<th><?=_("Extensions")?></th>
									<th><?=_("Permissions")?></th>
									<th><?=_("Destination")?></th>
									<th><?=_("Redirect Prefix")?></th>
								</tr>
							</thead>
							<tbody>
                                <?php foreach ($routes as $r):?>
								<tr>
									<td id="td_<?=$r?>">
										<?=$r?>
									</td>
									<td>
										<input name="range_<?=$r?>" id="range_<?=$r?>" value=<?=_("All")?> class="form-control" type="text" size="10">
									</td>
									<td>
										<span class="radioset">
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_SKIP" value="" class="form-control" type="radio" checked="checked"/>
											<label for="permission_<?=$r?>_SKIP"><?=_("No change")?></label>
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_YES" value="YES" class="form-control" type="radio"/>
											<label for="permission_<?=$r?>_YES"><?=_("Allow")?></label>
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_NO" value="NO" class="form-control" type="radio"/>
											<label for="permission_<?=$r?>_NO"><?=_("Deny")?></label>
											<input name="permission_<?=$r?>" id="permission_<?=$r?>_REDIRECT" value="REDIRECT" class="form-control" type="radio"/>
											<label for="permission_<?=$r?>_REDIRECT"><?=_("Redirect w/prefix")?></label>
										</span>
									</td>
									<td>
										<?=\drawselects("", "_$r", false, false, _("Use default"))?>
									</td>
									<td>
										<input name="prefix_$r" type="text" class="form-control" placeholder="<?=_("Prefix")?>" size="10"/>
									</td>
								</tr>
                                <?php endforeach?>
								<tr>
									<td colspan="5">
										<button name="update_permissions" type="submit" class="btn btn-primary"><?=_("Save Changes")?></button>
									</td>
								</tr>
							</tbody>
						</table>
					</form>

                    <hr>
                    <h4><i class="fa fa-list"></i> <?=_("Current Active Permissions")?></h4>
                    <p class="text-muted"><?=_("Below is the list of extensions explicitly configured in the database.")?></p>
                    
                    <table class="table table-striped table-hover table-condensed" data-toggle="table" data-pagination="true" data-search="true">
                        <thead>
                            <tr>
                                <th data-sortable="true"><?=_("Extension")?></th>
                                <th data-sortable="true"><?=_("Route Name")?></th>
                                <th data-sortable="true"><?=_("Allowed")?></th>
                                <th data-sortable="true"><?=_("Fail Destination")?></th>
                                <th data-sortable="true"><?=_("Prefix")?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($current_permissions)): ?>
                                <?php foreach($current_permissions as $perm): ?>
                                    <tr>
                                        <td><?=$perm['exten']?></td>
                                        <td><?=$perm['routename']?></td>
                                        <td>
                                            <?php 
                                            if($perm['allowed'] == 'YES') {
                                                echo '<span class="label label-success">YES</span>';
                                            } elseif ($perm['allowed'] == 'NO') {
                                                echo '<span class="label label-danger">NO</span>';
                                            } else {
                                                echo $perm['allowed'];
                                            }
                                            ?>
                                        </td>
                                        <td><?=$perm['faildest']?></td>
                                        <td><?=$perm['prefix']?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center"><em><?=_("No specific permissions found.")?></em></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <br>
                    <form method="post">
						<h4><?=_("Default Destination if Denied")?></h4>
						<p>
							<?=_("Select the destination for calls when they are denied without specifying a destination.")?>
						</p>
						<p>
							<?=\drawselects($rp->getDefaultDest(), "faildest")?>
						</p>
						<p>
							<button name="update_default" type="submit" class="btn btn-default"><?=_("Change Destination")?></button>
						</p>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
